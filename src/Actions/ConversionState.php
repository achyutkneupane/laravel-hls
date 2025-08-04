<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use Illuminate\Database\Eloquent\Model;

/**
 * State container for conversion process.
 */
final class ConversionState
{
    public string $inputPath;
    public string $outputFolder;
    public Model $model;
    public bool $isRetry;
    public bool $useGpu;
    public bool $wasGpuUsed = false;
    public ?string $gpuType = null;

    public function __construct(string $inputPath, string $outputFolder, Model $model, bool $isRetry)
    {
        $this->inputPath = $inputPath;
        $this->outputFolder = $outputFolder;
        $this->model = $model;
        $this->isRetry = $isRetry;
        $this->useGpu = config('hls.use_gpu_acceleration', false);
    }
}
