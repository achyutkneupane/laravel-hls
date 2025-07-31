<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use AchyutN\LaravelHLS\Jobs\UpdateConversionProgress;
use Exception;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\Video\H264;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Prompts\Progress;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;

final class ConvertToHLS
{
    private static ?string $ffmpegPath = null;
    private static ?string $smiPath = null;

    /**
     * Convert a video file to HLS format.
     */
    public static function convertToHLS(string $inputPath, string $outputFolder, Model $model, bool $isRetry = false): void
    {
        $startTime = microtime(true);
        $wasGpuUsed = false;

        try {
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
            $useGpu = config('hls.use_gpu_acceleration', false);

            foreach ($lowerResolutions as $resolution => $res) {
                $bitrate = $kiloBitRates[$resolution] ?? 1000;
                $format = self::createVideoFormat((int) $bitrate, $res, $isRetry);
                $formats[] = $format;

                // ✅ FINAL POLISH: More accurate GPU usage tracking
                if ($useGpu && !$isRetry && $format instanceof H264) {
                    $wasGpuUsed = true;
                }
            }
            if (empty($formats)) {
                $format = self::createVideoFormat((int) $fileBitrate, $fileResolution, $isRetry);
                $formats[] = $format;
                if ($useGpu && !$isRetry && $format instanceof H264) {
                    $wasGpuUsed = true;
                }
            }

            $export = FFMpeg::fromDisk($videoDisk)->open($inputPath)->exportForHLS()->toDisk($hlsDisk);
            foreach ($formats as $format) {
                $export->addFormat($format);
            }
            info('Started conversion for resolutions: '.implode(', ', array_keys($lowerResolutions)));

            $progress = progress(label: 'Converting video to HLS format...', steps: 100);
            $progress->start();
            $export->onProgress(function ($percentage) use ($model, $progress, $startTime): void {
                $progress->hint(self::estimateTime($startTime, $percentage));
                $progress->advance();
                UpdateConversionProgress::dispatch($model, $percentage);
            });

            if (config('hls.enable_encryption')) {
                $export->withRotatingEncryptionKey(fn ($filename, $contents) => Storage::disk($secretsDisk)->put("{$outputFolder}/{$secretsOutputPath}/{$filename}", $contents));
            }

            $export->save("{$outputFolder}/{$hlsOutputPath}/playlist.m3u8");
            $progress->finish();

            if ($wasGpuUsed) {
                self::logGPUPerformance($startTime);
            }

        } catch (Exception $e) {
            if ($wasGpuUsed) {
                Log::warning('GPU conversion failed, falling back to CPU: ' . $e->getMessage());
                self::convertToHLSWithCPU($inputPath, $outputFolder, $model);
            } else {
                throw new Exception("HLS conversion failed: {$e->getMessage()}");
            }
        } finally {
            FFMpeg::cleanupTemporaryFiles();
        }
    }

    private static function convertToHLSWithCPU(string $inputPath, string $outputFolder, Model $model): void
    {
        Log::info("Retrying conversion for {$inputPath} using CPU.");
        self::convertToHLS($inputPath, $outputFolder, $model, true);
    }

    private static function estimateTime(float $startTime, float $progress): string
    {
        $elapsed = microtime(true) - $startTime;
        $remainingSteps = 100 - $progress;
        $etaSeconds = ($progress > 0) ? ($elapsed / $progress) * $remainingSteps : 0;
        return 'Estimated time remaining: '.gmdate('H:i:s', (int) $etaSeconds);
    }

    private static function extractResolution(string $resolution): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', $resolution, $matches)) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }
        throw new Exception("Invalid resolution format: {$resolution}.");
    }

    private static function renameResolution(string $resolution): string
    {
        $parts = explode('x', $resolution);
        if (count($parts) !== 2) {
            throw new Exception("Invalid resolution format: {$resolution}.");
        }
        return "{$parts[0]}:{$parts[1]}";
    }

    private static function createVideoFormat(int $bitrate, string $resolution, bool $isRetry): X264|H264
    {
        $useGpu = config('hls.use_gpu_acceleration', false);
        if ($useGpu && !$isRetry && self::isGPUAvailable()) {
            return self::createGPUFormat($bitrate, $resolution);
        }
        if ($useGpu && !$isRetry) {
            Log::warning('GPU acceleration enabled but GPU not available. Falling back to CPU.');
        }
        return self::createCPUFormat($bitrate, $resolution);
    }

    private static function createGPUFormat(int $bitrate, string $resolution): H264
    {
        self::validateGPUConfig();
        $gpuDevice = config('hls.gpu_device', 'auto');
        $gpuPreset = config('hls.gpu_preset', 'fast');
        $gpuProfile = config('hls.gpu_profile', 'high');

        $format = new H264();
        $format->setKiloBitrate($bitrate)->setAudioKiloBitrate(128);
        $additionalParams = [
            '-vf', 'scale='.self::renameResolution($resolution), '-c:v', 'h264_nvenc',
            '-c:a', 'aac', '-preset', $gpuPreset, '-profile:v', $gpuProfile,
            '-rc', 'cbr', '-b:v', $bitrate.'k', '-maxrate', $bitrate.'k',
            '-bufsize', ($bitrate * 2).'k',
        ];

        if ($gpuDevice !== 'auto') {
            $additionalParams[] = '-gpu_device';
            $additionalParams[] = $gpuDevice;
        }
        $format->setAdditionalParameters($additionalParams);
        return $format;
    }

    private static function createCPUFormat(int $bitrate, string $resolution): X264
    {
        $format = new X264('libx264', 'aac');
        $format->setKiloBitrate($bitrate)->setAudioKiloBitrate(128);
        $format->setAdditionalParameters(['-vf', 'scale='.self::renameResolution($resolution), '-preset', 'veryfast', '-crf', '22']);
        return $format;
    }

    private static function isGPUAvailable(): bool
    {
        try {
            if (!function_exists('shell_exec')) return false;

            self::$ffmpegPath = self::findBinary('ffmpeg');
            if (empty(self::$ffmpegPath)) {
                Log::warning('GPU check failed: ffmpeg binary not found.');
                return false;
            }
            $encoders = shell_exec(self::$ffmpegPath . ' -hide_banner -encoders 2>&1');
            if (!str_contains($encoders, 'h264_nvenc')) {
                Log::warning('GPU check failed: ffmpeg build does not support h264_nvenc.');
                return false;
            }
            return self::hasSufficientGPUMemory() && self::isGPUTempOK();
        } catch (Exception $e) {
            Log::error('GPU availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function hasSufficientGPUMemory(): bool
    {
        self::$smiPath = self::findBinary('nvidia-smi', ['C:\Program Files\NVIDIA Corporation\NVSMI\nvidia-smi.exe']);
        if (empty(self::$smiPath)) return true;
        $freeMemory = shell_exec(self::$smiPath . ' --query-gpu=memory.free --format=csv,noheader,nounits 2>&1');
        if (!is_numeric(trim($freeMemory))) return true;
        $minRequiredMemory = config('hls.gpu_min_memory_mb', 500);
        if ((int)$freeMemory < $minRequiredMemory) {
            Log::warning("GPU check failed: Insufficient free memory. Found: {$freeMemory}MB, Required: {$minRequiredMemory}MB.");
            return false;
        }
        return true;
    }

    private static function isGPUTempOK(): bool
    {
        if (empty(self::$smiPath)) return true;
        $temp = shell_exec(self::$smiPath . ' --query-gpu=temperature.gpu --format=csv,noheader,nounits 2>&1');
        if (!is_numeric(trim($temp))) return true;
        $maxTemp = config('hls.gpu_max_temp', 85);
        if ((int)$temp >= $maxTemp) {
            Log::warning("GPU check failed: Temperature too high. Current: {$temp}°C, Threshold: {$maxTemp}°C.");
            return false;
        }
        return true;
    }

    private static function validateGPUConfig(): void
    {
        // ✅ FINAL POLISH: Corrected preset list and added profile validation
        $preset = config('hls.gpu_preset', 'fast');
        $validPresets = ['slow', 'medium', 'fast', 'hq', 'll', 'llhq', 'lossless', 'losslesshq'];
        if (!in_array($preset, $validPresets)) {
            Log::warning("Invalid GPU preset '{$preset}', using 'fast' instead.");
            config(['hls.gpu_preset' => 'fast']);
        }
        $profile = config('hls.gpu_profile', 'high');
        $validProfiles = ['baseline', 'main', 'high'];
        if (!in_array($profile, $validProfiles)) {
            Log::warning("Invalid GPU profile '{$profile}', using 'high' instead.");
            config(['hls.gpu_profile' => 'high']);
        }
    }

    private static function logGPUPerformance(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        Log::info("GPU conversion completed in " . round($duration, 2) . " seconds.");
    }

    private static function findBinary(string $name, array $customPaths = []): string
    {
        $paths = array_merge([$name], $customPaths, ['/usr/bin/' . $name, '/usr/local/bin/' . $name]);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCommand = $isWindows ? 'where' : 'command -v';
        foreach ($paths as $path) {
            $output = shell_exec("$testCommand $path 2>&1");
            if (!empty($output) && !str_contains($output, 'not found')) return trim($path);
        }
        return '';
    }
}
