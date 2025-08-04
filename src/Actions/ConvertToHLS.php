<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use AchyutN\LaravelHLS\Services\GPUDetectionService;
use AchyutN\LaravelHLS\Services\VideoFormatService;
use AchyutN\LaravelHLS\Services\EncryptionService;
use AchyutN\LaravelHLS\Services\VideoAnalysisService;
use AchyutN\LaravelHLS\Traits\DebugLoggable;
use AchyutN\LaravelHLS\Events\HLSConversionCompleted;
use AchyutN\LaravelHLS\Events\HLSConversionFailed;
use AchyutN\LaravelHLS\Jobs\UpdateConversionProgress;
use Exception;
use FFMpeg\Format\Video\X264;
use Illuminate\Database\Eloquent\Model;
use Laravel\Prompts\Progress;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;

final class ConvertToHLS
{
    use DebugLoggable;
    private GPUDetectionService $gpuService;
    private VideoFormatService $formatService;
    private EncryptionService $encryptionService;
    private VideoAnalysisService $analysisService;

    public function __construct()
    {
        $this->gpuService = new GPUDetectionService();
        $this->formatService = new VideoFormatService();
        $this->encryptionService = new EncryptionService();
        $this->analysisService = new VideoAnalysisService();
    }

    /**
     * Convert a video file to HLS format.
     */
    public static function convertToHLS(string $inputPath, string $outputFolder, Model $model, bool $isRetry = false): void
    {
        $converter = new self();
        $converter->convert($inputPath, $outputFolder, $model, $isRetry);
    }

    /**
     * Main conversion orchestration.
     */
    private function convert(string $inputPath, string $outputFolder, Model $model, bool $isRetry): void
    {
        $startTime = microtime(true);
        $conversionState = new ConversionState($inputPath, $outputFolder, $model, $isRetry);

        $this->logConversionStart($conversionState);

        try {
            $videoInfo = $this->analysisService->analyzeVideo($inputPath, $model);
            $formats = $this->formatService->createFormats($videoInfo, $conversionState, $this->gpuService);
            $this->performConversion($conversionState, $formats, $videoInfo, $startTime);
            $this->logCompletion($conversionState, $startTime, $videoInfo);
        } catch (Exception $e) {
            $this->handleError($e, $conversionState, $videoInfo, $startTime);
        } finally {
            FFMpeg::cleanupTemporaryFiles();
        }
    }

    /**
     * Log the start of conversion.
     */
    private function logConversionStart(ConversionState $state): void
    {
        $this->debugLog("Starting HLS conversion for: {$state->inputPath}");
        $this->debugLog("GPU acceleration enabled: " . ($state->useGpu ? 'YES' : 'NO'));

        if ($state->isRetry) {
            $this->debugLog("This is a retry attempt using CPU fallback");
        }
    }

    /**
     * Perform the actual HLS conversion.
     */
    private function performConversion(ConversionState $state, array $formats, array $videoInfo, float $startTime): void
    {
        $export = $this->createExport($state, $formats, $videoInfo);
        $this->setupProgress($export, $state, $videoInfo, $startTime);
        $this->encryptionService->setupEncryption($export, $state, $videoInfo);
        $this->executeConversion($export, $state, $videoInfo);
    }

    /**
     * Create the FFmpeg export object.
     */
    private function createExport(ConversionState $state, array $formats, array $videoInfo)
    {
        $export = FFMpeg::fromDisk($videoInfo['videoDisk'])
            ->open($state->inputPath)
            ->exportForHLS()
            ->toDisk($videoInfo['hlsDisk']);

        foreach ($formats as $format) {
            $export->addFormat($format);
        }

        return $export;
    }

    /**
     * Setup progress tracking.
     */
    private function setupProgress($export, ConversionState $state, array $videoInfo, float $startTime): void
    {
        $this->debugLog("🔄 Starting conversion process...");
        $this->debugLog("🎯 Target: {$state->outputFolder}/{$videoInfo['hlsOutputPath']}/playlist.m3u8");

        $resolutions = config('hls.resolutions');
        info('Started conversion for resolutions: ' . implode(', ', array_keys($resolutions)));

        $progress = progress(label: 'Converting video to HLS format...', steps: 100);
        $progress->start();

        $export->onProgress(function ($percentage) use ($progress, $startTime, $state): void {
            $progress->hint($this->estimateTime($startTime, $percentage));
            $progress->advance();
            UpdateConversionProgress::dispatch($state->model, $percentage);
        });
    }

    /**
     * Execute the conversion.
     */
    private function executeConversion($export, ConversionState $state, array $videoInfo): void
    {
        $export->save("{$state->outputFolder}/{$videoInfo['hlsOutputPath']}/playlist.m3u8");
    }

    /**
     * Log completion status and dispatch success event.
     */
    private function logCompletion(ConversionState $state, float $startTime, array $videoInfo): void
    {
        $conversionTime = microtime(true) - $startTime;

        if ($state->wasGpuUsed) {
            $this->logGPUPerformance($startTime);
            if ($state->gpuType === 'apple') {
                $this->debugLog("✅ Apple Silicon conversion completed successfully!");
            } else {
                $this->debugLog("✅ NVIDIA GPU conversion completed successfully!");
            }
        } else {
            $this->debugLog("✅ CPU conversion completed successfully!");
        }

        // Dispatch success event
        HLSConversionCompleted::dispatch(
            $state->inputPath,
            $state->outputFolder,
            $state->model,
            $state->wasGpuUsed,
            $state->gpuType,
            $conversionTime,
            $videoInfo,
            $state->isRetry
        );
    }

    /**
     * Handle conversion errors.
     */
    private function handleError(Exception $e, ConversionState $state, array $videoInfo, float $startTime): void
    {
        $errorMessage = $e->getMessage();
        $conversionTime = microtime(true) - $startTime;

        if ($this->isEncryptionError($errorMessage)) {
            $this->debugLog("🔐 Encryption error detected: {$errorMessage}", 'warning');
            $this->debugLog("🔄 Retrying without encryption...", 'warning');
            $this->retryWithoutEncryption($state);
            return;
        }

        if ($state->wasGpuUsed) {
            $this->debugLog('GPU conversion failed, falling back to CPU: ' . $errorMessage, 'warning');
            $this->retryWithCPU($state);
        } else {
            // Dispatch failure event before throwing
            HLSConversionFailed::dispatch(
                $state->inputPath,
                $state->outputFolder,
                $state->model,
                $errorMessage,
                $state->wasGpuUsed,
                $state->gpuType,
                $conversionTime,
                $videoInfo,
                $state->isRetry
            );

            throw new Exception("HLS conversion failed: {$errorMessage}");
        }
    }

    /**
     * Check if error is encryption-related.
     */
    private function isEncryptionError(string $errorMessage): bool
    {
        return str_contains($errorMessage, 'no key URI specified') ||
               str_contains($errorMessage, 'Invalid argument') ||
               str_contains($errorMessage, 'key info file');
    }

    /**
     * Retry conversion without encryption.
     */
    private function retryWithoutEncryption(ConversionState $state): void
    {
        $this->debugLog("Retrying conversion for {$state->inputPath} without encryption.");

        $originalEncryption = config('hls.enable_encryption');
        config(['hls.enable_encryption' => false]);

        try {
            $this->convert($state->inputPath, $state->outputFolder, $state->model, false);
        } finally {
            config(['hls.enable_encryption' => $originalEncryption]);
        }
    }

    /**
     * Retry conversion with CPU.
     */
    private function retryWithCPU(ConversionState $state): void
    {
        $this->debugLog("Retrying conversion for {$state->inputPath} using CPU.");
        $this->convert($state->inputPath, $state->outputFolder, $state->model, true);
    }

    /**
     * Estimate remaining time for conversion.
     */
    private function estimateTime(float $startTime, float $progress): string
    {
        $elapsed = microtime(true) - $startTime;
        $remainingSteps = 100 - $progress;
        $etaSeconds = ($progress > 0) ? ($elapsed / $progress) * $remainingSteps : 0;
        return 'Estimated time remaining: '.gmdate('H:i:s', (int) $etaSeconds);
    }

    /**
     * Log GPU performance metrics.
     */
    private function logGPUPerformance(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        $this->debugLog("GPU conversion completed in " . round($duration, 2) . " seconds.");
    }


}
