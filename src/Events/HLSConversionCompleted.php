<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when HLS conversion completes successfully.
 */
final class HLSConversionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $inputPath,
        public readonly string $outputFolder,
        public readonly Model $model,
        public readonly bool $wasGpuUsed,
        public readonly ?string $gpuType,
        public readonly float $conversionTime,
        public readonly array $videoInfo,
        public readonly bool $wasRetry = false
    ) {
    }

    /**
     * Get the playlist path that was generated.
     */
    public function getPlaylistPath(): string
    {
        return "{$this->outputFolder}/{$this->videoInfo['hlsOutputPath']}/playlist.m3u8";
    }

    /**
     * Get the output directory path.
     */
    public function getOutputDirectory(): string
    {
        return "{$this->outputFolder}/{$this->videoInfo['hlsOutputPath']}";
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
