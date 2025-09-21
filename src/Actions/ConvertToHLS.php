<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use AchyutN\LaravelHLS\Jobs\UpdateConversionProgress;
use Exception;
use FFMpeg\Format\Video\X264;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Exception\RuntimeException;

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

        $resolutions = $model->getHLSResolutions();

        $kiloBitRates = config('hls.bitrates');

        $videoDisk = $model->getVideoDisk();
        $hlsDisk = $model->getHlsDisk();
        $secretsDisk = $model->getSecretsDisk();
        $hlsOutputPath = $model->getHLSOutputPath();
        $secretsOutputPath = $model->getHLSSecretsOutputPath();

        try {
            $media = FFMpeg::fromDisk($videoDisk)->open($inputPath);
            $fileBitrate = $media->getFormat()->get('bit_rate') / 1000;
            $streamVideo = $media->getVideoStream()->getDimensions();
            $fileResolution = "{$streamVideo->getWidth()}x{$streamVideo->getHeight()}";
        } catch (Exception $e) {
            FFMpeg::cleanupTemporaryFiles();
            throw new RuntimeException('Failed to open or probe video file.', $e->getCode(), $e);
        }

        $formats = [];

        $lowerResolutions = array_filter($resolutions, fn ($resolution): bool => self::extractResolution($resolution)['height'] <= self::extractResolution($fileResolution)['height']);

        foreach ($lowerResolutions as $resolution => $res) {
            $bitrate = $kiloBitRates[$resolution] ?? 1000;
            $formats[] = (new X264)
                ->setKiloBitrate($bitrate)
                ->setAudioKiloBitrate(128)
                ->setAdditionalParameters([
                    '-vf', 'scale='.self::renameResolution($res),
                    '-tune', 'zerolatency',
                    '-preset', 'veryfast',
                    '-crf', '22',
                ]);
        }

        if ($formats === []) {
            $formats[] = (new X264)
                ->setKiloBitrate($fileBitrate)
                ->setAudioKiloBitrate(128)
                ->setAdditionalParameters([
                    '-vf', 'scale='.self::renameResolution($fileResolution),
                    '-tune', 'zerolatency',
                    '-preset', 'veryfast',
                    '-crf', '22',
                ]);
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
            throw new RuntimeException("Failed to prepare formats for HLS conversion: {$e->getMessage()}", $e->getCode(), $e);
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

        throw new InvalidArgumentException("Invalid resolution format: {$resolution}. Expected format is '{width}x{height}'.");
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
            throw new InvalidArgumentException("Invalid resolution format: {$resolution}. Expected format is '{width}x{height}'.");
        }

        return "{$parts[0]}:{$parts[1]}";
    }
}
