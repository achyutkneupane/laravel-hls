<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when HLS conversion fails.
 */
final class HLSConversionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $inputPath,
        public readonly string $outputFolder,
        public readonly Model $model,
        public readonly string $errorMessage,
        public readonly bool $wasGpuUsed,
        public readonly ?string $gpuType,
        public readonly float $conversionTime,
        public readonly array $videoInfo,
        public readonly bool $wasRetry = false
    ) {
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Check if GPU was used for conversion.
     */
    public function wasGpuUsed(): bool
    {
        return $this->wasGpuUsed;
    }

    /**
     * Get the GPU type that was used (if any).
     */
    public function getGpuType(): ?string
    {
        return $this->gpuType;
    }

    /**
     * Get the conversion time in seconds.
     */
    public function getConversionTime(): float
    {
        return $this->conversionTime;
    }

    /**
     * Get the conversion time formatted as a string.
     */
    public function getFormattedConversionTime(): string
    {
        return gmdate('H:i:s', (int) $this->conversionTime);
    }
}
