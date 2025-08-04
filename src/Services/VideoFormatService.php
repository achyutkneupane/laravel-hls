<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Services;

use AchyutN\LaravelHLS\Traits\DebugLoggable;
use Exception;
use FFMpeg\Format\Video\X264;

final class VideoFormatService
{
    use DebugLoggable;
    // Configuration constants
    private const GPU_PRESETS = [
        'SLOW' => 'slow',
        'MEDIUM' => 'medium',
        'FAST' => 'fast',
        'HQ' => 'hq',
        'LL' => 'll',
        'LLHQ' => 'llhq',
        'LOSSLESS' => 'lossless',
        'LOSSLESSHQ' => 'losslesshq'
    ];

    private const GPU_PROFILES = [
        'BASELINE' => 'baseline',
        'MAIN' => 'main',
        'HIGH' => 'high'
    ];

    // Default configuration values
    private const DEFAULT_GPU_PRESET = 'fast';
    private const DEFAULT_GPU_PROFILE = 'high';
    private const DEFAULT_BITRATE = 1000;
    private const DEFAULT_AUDIO_BITRATE = 128;

    /**
     * Create video formats for all resolutions.
     */
    public function createFormats(array $videoInfo, object $conversionState, GPUDetectionService $gpuService): array
    {
        $resolutions = config('hls.resolutions');
        $kiloBitRates = config('hls.bitrates');

        $lowerResolutions = array_filter($resolutions, function ($resolution) use ($videoInfo): bool {
            return $this->extractResolution($resolution)['height'] <= $this->extractResolution($videoInfo['resolution'])['height'];
        });

        $this->debugLog("📊 Processing " . count($lowerResolutions) . " resolutions: " . implode(', ', array_keys($lowerResolutions)));
        $this->debugLog("📹 Original video resolution: {$videoInfo['resolution']}, bitrate: {$videoInfo['bitrate']}k");

        $formats = [];
        foreach ($lowerResolutions as $resolution => $res) {
            $formats[] = $this->createFormatForResolution($resolution, $res, $conversionState, $gpuService);
        }

        if (empty($formats)) {
            $formats[] = $this->createFallbackFormat($videoInfo, $conversionState, $gpuService);
        }

        return $formats;
    }

    /**
     * Create a format for a specific resolution.
     */
    private function createFormatForResolution(string $resolution, string $res, object $conversionState, GPUDetectionService $gpuService): X264
    {
        $bitrate = config('hls.bitrates')[$resolution] ?? self::DEFAULT_BITRATE;
        $this->debugLog("🎬 Creating format for resolution: {$resolution} with bitrate: {$bitrate}k");

        $format = $this->createVideoFormat((int) $bitrate, $res, $conversionState, $gpuService);
        $this->trackGPUUsage($format, $resolution, $conversionState, $gpuService);

        return $format;
    }

    /**
     * Track GPU usage and log format creation.
     */
    private function trackGPUUsage(X264 $format, string $resolution, object $conversionState, GPUDetectionService $gpuService): void
    {
        if ($conversionState->useGpu && !$conversionState->isRetry && $this->isGPUFormat($format)) {
            $conversionState->wasGpuUsed = true;
            if ($conversionState->gpuType === null) {
                $conversionState->gpuType = $gpuService->detectBestGPU();
            }
            $this->logGPUFormatCreation($resolution, $conversionState->gpuType);
        } else {
            $this->debugLog("🖥️ CPU format created for resolution: {$resolution}");
        }
    }

    /**
     * Log GPU format creation.
     */
    private function logGPUFormatCreation(string $resolution, string $gpuType): void
    {
        if ($gpuType === 'apple') {
            $this->debugLog("🍎 Apple Silicon format created for resolution: {$resolution}");
        } else {
            $this->debugLog("🚀 NVIDIA GPU format created for resolution: {$resolution}");
        }
    }

    /**
     * Create fallback format when no resolutions are found.
     */
    private function createFallbackFormat(array $videoInfo, object $conversionState, GPUDetectionService $gpuService): X264
    {
        $this->debugLog("⚠️ No resolutions found, using original video format");
        $format = $this->createVideoFormat((int) $videoInfo['bitrate'], $videoInfo['resolution'], $conversionState, $gpuService);
        $this->trackGPUUsage($format, 'original', $conversionState, $gpuService);
        return $format;
    }

    /**
     * Create video format based on available hardware.
     */
    private function createVideoFormat(int $bitrate, string $resolution, object $conversionState, GPUDetectionService $gpuService): X264
    {
        if ($conversionState->useGpu && !$conversionState->isRetry) {
            $gpuType = $gpuService->detectBestGPU();

            if ($gpuType === 'nvidia') {
                $this->debugLog("🔍 NVIDIA GPU detected, creating NVIDIA format...");
                return $this->createNvidiaFormat($bitrate, $resolution);
            } elseif ($gpuType === 'apple') {
                $this->debugLog("🔍 Apple Silicon detected, creating Apple format...");
                return $this->createAppleFormat($bitrate, $resolution);
            } else {
                $this->debugLog('⚠️ GPU acceleration enabled but no compatible GPU found. Falling back to CPU.', 'warning');
            }
        }

        $this->debugLog("🔍 Creating CPU format...");
        return $this->createCPUFormat($bitrate, $resolution);
    }

    /**
     * Create NVIDIA GPU format for video encoding.
     */
    private function createNvidiaFormat(int $bitrate, string $resolution): X264
    {
        $this->validateGPUConfig();

        $gpuConfig = $this->getNvidiaGPUConfig();
        $this->logNvidiaFormatCreation($bitrate, $resolution, $gpuConfig);

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(self::DEFAULT_AUDIO_BITRATE);

        $additionalParams = $this->buildNvidiaFormatParameters($bitrate, $resolution, $gpuConfig);
        $format->setAdditionalParameters($additionalParams);

        $this->debugLog("✅ NVIDIA GPU format created successfully");
        return $format;
    }

    /**
     * Get NVIDIA GPU configuration settings.
     */
    private function getNvidiaGPUConfig(): array
    {
        return [
            'device' => config('hls.gpu_device', 'auto'),
            'preset' => config('hls.gpu_preset', self::DEFAULT_GPU_PRESET),
            'profile' => config('hls.gpu_profile', self::DEFAULT_GPU_PROFILE),
        ];
    }

    /**
     * Log NVIDIA format creation details.
     */
    private function logNvidiaFormatCreation(int $bitrate, string $resolution, array $gpuConfig): void
    {
        $this->debugLog("🚀 Creating NVIDIA GPU format with:");
        $this->debugLog("   - Device: {$gpuConfig['device']}");
        $this->debugLog("   - Preset: {$gpuConfig['preset']}");
        $this->debugLog("   - Profile: {$gpuConfig['profile']}");
        $this->debugLog("   - Bitrate: {$bitrate}k");
        $this->debugLog("   - Resolution: {$resolution}");
    }

    /**
     * Build NVIDIA format parameters.
     */
    private function buildNvidiaFormatParameters(int $bitrate, string $resolution, array $gpuConfig): array
    {
        $params = [
            '-vf', 'scale='.$this->renameResolution($resolution),
            '-c:v', 'h264_nvenc',  // Override the video codec
            '-preset', $gpuConfig['preset'],
            '-profile:v', $gpuConfig['profile'],
            '-rc', 'cbr',
            '-b:v', $bitrate.'k',
            '-maxrate', $bitrate.'k',
            '-bufsize', ($bitrate * 2).'k',
        ];

        if ($gpuConfig['device'] !== 'auto') {
            $params[] = '-gpu_device';
            $params[] = $gpuConfig['device'];
        }

        return $params;
    }

    /**
     * Create Apple Silicon format for video encoding.
     */
    private function createAppleFormat(int $bitrate, string $resolution): X264
    {
        $this->debugLog("🍎 Creating Apple Silicon format with:");
        $this->debugLog("   - Bitrate: {$bitrate}k");
        $this->debugLog("   - Resolution: {$resolution}");
        $this->debugLog("   - Encoder: h264_videotoolbox");
        $this->debugLog("   - Quality: medium");
        $this->debugLog("   - Realtime: true");

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(self::DEFAULT_AUDIO_BITRATE);
        $additionalParams = [
            '-vf', 'scale='.$this->renameResolution($resolution),
            '-c:v', 'h264_videotoolbox',  // Override the video codec
            '-profile:v', 'main',
            '-quality', 'medium',
            '-realtime', 'true',
            '-b:v', $bitrate.'k',
            '-maxrate', $bitrate.'k',
            '-bufsize', ($bitrate * 2).'k',
            '-allow_sw', '1',
        ];

        $format->setAdditionalParameters($additionalParams);

        $this->debugLog("✅ Apple Silicon format created successfully");
        return $format;
    }

    /**
     * Create CPU format for video encoding.
     */
    private function createCPUFormat(int $bitrate, string $resolution): X264
    {
        $this->debugLog("🖥️ Creating CPU format with:");
        $this->debugLog("   - Bitrate: {$bitrate}k");
        $this->debugLog("   - Resolution: {$resolution}");
        $this->debugLog("   - Preset: veryfast");
        $this->debugLog("   - CRF: 22");

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(self::DEFAULT_AUDIO_BITRATE);
        $format->setAdditionalParameters([
            '-vf', 'scale='.$this->renameResolution($resolution),
            '-preset', 'veryfast',
        ]);

        $this->debugLog("✅ CPU format created successfully");
        return $format;
    }

    /**
     * Validate and correct GPU configuration settings.
     */
    private function validateGPUConfig(): void
    {
        $this->validateGPUPreset();
        $this->validateGPUProfile();
    }

    /**
     * Validate GPU preset configuration.
     */
    private function validateGPUPreset(): void
    {
        $preset = config('hls.gpu_preset', self::DEFAULT_GPU_PRESET);
        $validPresets = array_values(self::GPU_PRESETS);

        if (!in_array($preset, $validPresets)) {
            $this->debugLog("Invalid GPU preset '{$preset}', using '" . self::DEFAULT_GPU_PRESET . "' instead.", 'warning');
            config(['hls.gpu_preset' => self::DEFAULT_GPU_PRESET]);
        }
    }

    /**
     * Validate GPU profile configuration.
     */
    private function validateGPUProfile(): void
    {
        $profile = config('hls.gpu_profile', self::DEFAULT_GPU_PROFILE);
        $validProfiles = array_values(self::GPU_PROFILES);

        if (!in_array($profile, $validProfiles)) {
            $this->debugLog("Invalid GPU profile '{$profile}', using '" . self::DEFAULT_GPU_PROFILE . "' instead.", 'warning');
            config(['hls.gpu_profile' => self::DEFAULT_GPU_PROFILE]);
        }
    }

    /**
     * Check if format uses GPU encoding.
     */
    private function isGPUFormat(X264 $format): bool
    {
        $params = $format->getAdditionalParameters();
        return (in_array('-c:v', $params) && in_array('h264_nvenc', $params)) ||
               (in_array('-c:v', $params) && in_array('h264_videotoolbox', $params));
    }

    /**
     * Extract resolution dimensions.
     */
    private function extractResolution(string $resolution): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', $resolution, $matches)) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }
        throw new Exception("Invalid resolution format: {$resolution}.");
    }

    /**
     * Rename resolution for FFmpeg scale filter.
     */
    private function renameResolution(string $resolution): string
    {
        $parts = explode('x', $resolution);
        if (count($parts) !== 2) {
            throw new Exception("Invalid resolution format: {$resolution}.");
        }
        return "{$parts[0]}:{$parts[1]}";
    }


}
