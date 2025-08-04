<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Listeners;

use AchyutN\LaravelHLS\Events\HLSConversionCompleted;
use AchyutN\LaravelHLS\Events\HLSConversionFailed;
use Illuminate\Support\Facades\Log;

/**
 * Example listener for HLS conversion events.
 *
 * This demonstrates how to listen to HLS conversion events.
 * You can create your own listeners and register them in your EventServiceProvider.
 */
final class ExampleHLSConversionListener
{
    /**
     * Handle the HLS conversion completed event.
     */
    public function handleCompleted(HLSConversionCompleted $event): void
    {
        Log::info('HLS conversion completed successfully!', [
            'input_path' => $event->inputPath,
            'output_folder' => $event->outputFolder,
            'playlist_path' => $event->getPlaylistPath(),
            'was_gpu_used' => $event->wasGpuUsed(),
            'gpu_type' => $event->getGpuType(),
            'conversion_time' => $event->getFormattedConversionTime(),
            'model_id' => $event->model->getKey(),
            'was_retry' => $event->wasRetry,
        ]);

        // Example: Update model with conversion results
        $event->model->update([
            'hls_playlist_path' => $event->getPlaylistPath(),
            'hls_conversion_time' => $event->getConversionTime(),
            'hls_was_gpu_used' => $event->wasGpuUsed(),
            'hls_gpu_type' => $event->getGpuType(),
            'hls_conversion_status' => 'completed',
        ]);

        // Example: Send notification to user
        // Notification::send($event->model->user, new HLSConversionCompletedNotification($event));

        // Example: Generate thumbnail from first segment
        // $this->generateThumbnail($event->getPlaylistPath());

        // Example: Update CDN cache
        // $this->updateCDNCache($event->getOutputDirectory());
    }

    /**
     * Handle the HLS conversion failed event.
     */
    public function handleFailed(HLSConversionFailed $event): void
    {
        Log::error('HLS conversion failed!', [
            'input_path' => $event->inputPath,
            'output_folder' => $event->outputFolder,
            'error_message' => $event->getErrorMessage(),
            'was_gpu_used' => $event->wasGpuUsed(),
            'gpu_type' => $event->getGpuType(),
            'conversion_time' => $event->getFormattedConversionTime(),
            'model_id' => $event->model->getKey(),
            'was_retry' => $event->wasRetry,
        ]);

        // Example: Update model with failure status
        $event->model->update([
            'hls_conversion_status' => 'failed',
            'hls_error_message' => $event->getErrorMessage(),
            'hls_conversion_time' => $event->getConversionTime(),
        ]);

        // Example: Send failure notification to user
        // Notification::send($event->model->user, new HLSConversionFailedNotification($event));

        // Example: Clean up partial files
        // $this->cleanupPartialFiles($event->outputFolder);

        // Example: Retry with different settings
        // $this->retryWithDifferentSettings($event);
    }

    /**
     * Example method to generate thumbnail from first segment.
     */
    private function generateThumbnail(string $playlistPath): void
    {
        // Implementation to generate thumbnail from first .ts segment
        // This is just an example - you would implement your own logic
    }

    /**
     * Example method to update CDN cache.
     */
    private function updateCDNCache(string $outputDirectory): void
    {
        // Implementation to update CDN cache
        // This is just an example - you would implement your own logic
    }

    /**
     * Example method to cleanup partial files.
     */
    private function cleanupPartialFiles(string $outputFolder): void
    {
        // Implementation to cleanup partial files
        // This is just an example - you would implement your own logic
    }

    /**
     * Example method to retry with different settings.
     */
    private function retryWithDifferentSettings(HLSConversionFailed $event): void
    {
        // Implementation to retry with different settings
        // This is just an example - you would implement your own logic
    }
}
