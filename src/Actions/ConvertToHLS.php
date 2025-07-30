<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use AchyutN\LaravelHLS\Jobs\UpdateConversionProgress;
use Exception;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Video\H264;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Laravel\Prompts\Progress;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;

final class ConvertToHLS
{
    /**
     * Convert a video file to HLS format with AES-128 encryption.
     *
     * @param  string  $inputPath  The path to the input video file.
     * @param  string  $outputFolder  The folder where the HLS output will be stored.
     * @param  Model  $model  The model instance (optional, used for progress tracking).
     *
     * @throws Exception If the conversion fails.
     */
    public static function convertToHLS(string $inputPath, string $outputFolder, Model $model): void
    {
        $startTime = microtime(true);

        $resolutions = config('hls.resolutions');
        $kiloBitRates = config('hls.bitrates');

        $videoDisk = $model->getVideoDisk();
        $hlsDisk = $model->getHlsDisk();
        $secretsDisk = $model->getSecretsDisk();
        $hlsOutputPath = $model->getHLSOutputPath();
        $secretsOutputPath = $model->getHLSSecretsOutputPath();

        $media = FFMpeg::fromDisk($videoDisk)->open($inputPath);
        $fileBitrate = $media->getFormat()->get('bit_rate') / 1000;
        $streamVideo = $media->getVideoStream()->getDimensions();
        $fileResolution = "{$streamVideo->getWidth()}x{$streamVideo->getHeight()}";

        $formats = [];

        $lowerResolutions = array_filter($resolutions, fn ($resolution): bool => self::extractResolution($resolution)['height'] <= self::extractResolution($fileResolution)['height']);

        foreach ($lowerResolutions as $resolution => $res) {
            $bitrate = $kiloBitRates[$resolution] ?? 1000;
            $formats[] = self::createVideoFormat($bitrate, $res);
        }

        if ($formats === []) {
            $formats[] = self::createVideoFormat($fileBitrate, $fileResolution);
        }

        try {
            $export = FFMpeg::fromDisk($videoDisk)
                ->open($inputPath)
                ->exportForHLS()
                ->toDisk($hlsDisk);

            foreach ($formats as $format) {
                $export->addFormat($format);
            }

            info('Started conversion for resolutions: '.implode(', ', array_keys($lowerResolutions)));

            $progress = progress(
                label: 'Converting video to HLS format...',
                steps: 100,
                hint: 'Estimated time remaining: Calculating...',
            );
            $progress->start();

            $export->onProgress(function ($percentage) use ($model, $progress, $startTime): void {
                $estimatedTime = self::estimateTime(
                    startTime: $startTime,
                    progress: $percentage
                );
                $progress->hint($estimatedTime);
                $progress->advance();
                UpdateConversionProgress::dispatch($model, $percentage);
            });

            if (config('hls.enable_encryption')) {
                $export
                    ->withRotatingEncryptionKey(function ($filename, $contents) use ($outputFolder, $secretsDisk, $secretsOutputPath): void {
                        Storage::disk($secretsDisk)->put("{$outputFolder}/{$secretsOutputPath}/{$filename}", $contents);
                    });
            }

            $export->save("{$outputFolder}/{$hlsOutputPath}/playlist.m3u8");

            FFMpeg::cleanupTemporaryFiles();

            $progress->finish();
        } catch (Exception $e) {
            FFMpeg::cleanupTemporaryFiles();
            throw new Exception("Failed to prepare formats for HLS conversion: {$e->getMessage()}");
        }
    }

    /**
     * Calculate the estimated time remaining.
     */
    private static function estimateTime(float $startTime, float $progress): string
    {
        $elapsed = microtime(true) - $startTime;
        $remainingSteps = 100 - $progress;
        $etaSeconds = ($progress > 0) ? ($elapsed / $progress) * $remainingSteps : 0;

        return 'Estimated time remaining: '.gmdate('H:i:s', (int) $etaSeconds);
    }

    /**
     * Extract width and height from a resolution string.
     *
     * @param  string  $resolution  The resolution string in the format '{width}x{height}'.
     * @return array An associative array with 'width' and 'height' keys.
     *
     * @throws Exception If the resolution string is not in the correct format.
     */
    private static function extractResolution(string $resolution): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', $resolution, $matches)) {
            return [
                'width' => (int) $matches[1],
                'height' => (int) $matches[2],
            ];
        }

        throw new Exception("Invalid resolution format: {$resolution}. Expected format is '{width}x{height}'.");
    }

    /**
     * Rename resolution from '{width}x{height}' to 'width:height'.
     *
     * @param  string  $resolution  The resolution string in the format '{width}x{height}'.
     * @return string The resolution string in the format 'width:height'.
     *
     * @throws Exception
     */
    private static function renameResolution(string $resolution): string
    {
        $parts = explode('x', $resolution);
        if (count($parts) !== 2) {
            throw new Exception("Invalid resolution format: {$resolution}. Expected format is '{width}x{height}'.");
        }

        return "{$parts[0]}:{$parts[1]}";
    }

    /**
     * Create a video format with appropriate encoder based on GPU configuration.
     *
     * @param  int  $bitrate  The bitrate in kbps.
     * @param  string  $resolution  The resolution string in the format '{width}x{height}'.
     * @return X264|H264 The video format instance.
     */
    private static function createVideoFormat(int $bitrate, string $resolution): X264|H264
    {
        $useGpu = config('hls.use_gpu_acceleration', false);

        if ($useGpu) {
            return self::createGPUFormat($bitrate, $resolution);
        }

        return self::createCPUFormat($bitrate, $resolution);
    }

        /**
     * Create a GPU-accelerated video format using NVIDIA NVENC.
     *
     * @param  int  $bitrate  The bitrate in kbps.
     * @param  string  $resolution  The resolution string in the format '{width}x{height}'.
     * @return H264 The video format instance.
     * @throws Exception If GPU acceleration is not available.
     */
    private static function createGPUFormat(int $bitrate, string $resolution): H264
    {
        if (!self::isGPUAvailable()) {
            throw new Exception('GPU acceleration is enabled but NVIDIA GPU with NVENC support is not available. Please ensure NVIDIA drivers are installed and NVENC is supported.');
        }

        $gpuDevice = config('hls.gpu_device', 'auto');
        $gpuPreset = config('hls.gpu_preset', 'fast');
        $gpuProfile = config('hls.gpu_profile', 'high');

        $format = new H264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);

        $additionalParams = [
            '-vf', 'scale='.self::renameResolution($resolution),
            '-c:v', 'h264_nvenc',
            '-preset', $gpuPreset,
            '-profile:v', $gpuProfile,
            '-rc', 'vbr',
            '-cq', '22',
        ];

        if ($gpuDevice !== 'auto') {
            $additionalParams[] = '-gpu';
            $additionalParams[] = $gpuDevice;
        }

        $format->setAdditionalParameters($additionalParams);

        return $format;
    }

    /**
     * Create a CPU-based video format using X264.
     *
     * @param  int  $bitrate  The bitrate in kbps.
     * @param  string  $resolution  The resolution string in the format '{width}x{height}'.
     * @return X264 The video format instance.
     */
    private static function createCPUFormat(int $bitrate, string $resolution): X264
    {
        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $format->setAdditionalParameters([
            '-vf', 'scale='.self::renameResolution($resolution),
            '-tune', 'zerolatency',
            '-preset', 'veryfast',
            '-crf', '22',
        ]);

        return $format;
    }

    /**
     * Check if NVIDIA GPU with NVENC support is available.
     *
     * @return bool True if GPU acceleration is available, false otherwise.
     */
    private static function isGPUAvailable(): bool
    {
        // Check if ffmpeg supports NVENC
        $output = [];
        $returnCode = 0;

        exec('ffmpeg -hide_banner -encoders 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            return false;
        }

        $output = implode("\n", $output);

        // Check for h264_nvenc encoder
        return str_contains($output, 'h264_nvenc');
    }
}
