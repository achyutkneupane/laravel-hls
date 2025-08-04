<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Traits;

use Illuminate\Support\Facades\Log;

trait DebugLoggable
{
    /**
     * Log a message only if debug mode is enabled.
     */
    private function debugLog(string $message, string $level = 'info'): void
    {
        if (config('hls.debug', false)) {
            switch ($level) {
                case 'warning':
                    Log::warning($message);
                    break;
                case 'error':
                    Log::error($message);
                    break;
                default:
                    Log::info($message);
                    break;
            }
        }
    }
}
