<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Services;

use Illuminate\Database\Eloquent\Model;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

final class VideoAnalysisService
{
    /**
     * Analyze video and extract information.
     */
    public function analyzeVideo(string $inputPath, Model $model): array
    {
        $media = FFMpeg::fromDisk($model->getVideoDisk())->open($inputPath);
        $streamVideo = $media->getVideoStream()->getDimensions();

        return [
            'resolution' => "{$streamVideo->getWidth()}x{$streamVideo->getHeight()}",
            'bitrate' => $media->getFormat()->get('bit_rate') / 1000,
            'videoDisk' => $model->getVideoDisk(),
            'hlsDisk' => $model->getHlsDisk(),
            'secretsDisk' => $model->getSecretsDisk(),
            'hlsOutputPath' => $model->getHLSOutputPath(),
            'secretsOutputPath' => $model->getHLSSecretsOutputPath(),
        ];
    }
}
