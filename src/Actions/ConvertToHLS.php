<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use AchyutN\LaravelHLS\Jobs\UpdateConversionProgress;
use Exception;
use FFMpeg\Format\Video\X264;
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
        $useGpu = config('hls.use_gpu_acceleration', false);

        Log::info("Starting HLS conversion for: {$inputPath}");
        Log::info("GPU acceleration enabled: " . ($useGpu ? 'YES' : 'NO'));

        if ($isRetry) {
            Log::info("This is a retry attempt using CPU fallback");
        }

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

            Log::info("📊 Processing " . count($lowerResolutions) . " resolutions: " . implode(', ', array_keys($lowerResolutions)));
            Log::info("📹 Original video resolution: {$fileResolution}, bitrate: {$fileBitrate}k");

            foreach ($lowerResolutions as $resolution => $res) {
                $bitrate = $kiloBitRates[$resolution] ?? 1000;
                Log::info("🎬 Creating format for resolution: {$resolution} with bitrate: {$bitrate}k");

                $format = self::createVideoFormat((int) $bitrate, $res, $isRetry);
                $formats[] = $format;

                // ✅ FINAL POLISH: More accurate GPU usage tracking
                if ($useGpu && !$isRetry && self::isGPUFormat($format)) {
                    $wasGpuUsed = true;
                    $gpuType = self::detectBestGPU();
                    if ($gpuType === 'apple') {
                        Log::info("🍎 Apple Silicon format created for resolution: {$resolution}");
                    } else {
                        Log::info("🚀 NVIDIA GPU format created for resolution: {$resolution}");
                    }
                } else {
                    Log::info("🖥️ CPU format created for resolution: {$resolution}");
                }
            }
            if (empty($formats)) {
                Log::info("⚠️ No resolutions found, using original video format");
                $format = self::createVideoFormat((int) $fileBitrate, $fileResolution, $isRetry);
                $formats[] = $format;
                if ($useGpu && !$isRetry && self::isGPUFormat($format)) {
                    $wasGpuUsed = true;
                    $gpuType = self::detectBestGPU();
                    if ($gpuType === 'apple') {
                        Log::info("🍎 Apple Silicon format created for original resolution");
                    } else {
                        Log::info("🚀 NVIDIA GPU format created for original resolution");
                    }
                } else {
                    Log::info("🖥️ CPU format created for original resolution");
                }
            }

            $export = FFMpeg::fromDisk($videoDisk)->open($inputPath)->exportForHLS()->toDisk($hlsDisk);
            foreach ($formats as $format) {
                $export->addFormat($format);
            }

            Log::info("🔄 Starting conversion process...");
            Log::info("🎯 Target: {$outputFolder}/{$hlsOutputPath}/playlist.m3u8");
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
                $gpuType = self::detectBestGPU();
                if ($gpuType === 'apple') {
                    Log::info("✅ Apple Silicon conversion completed successfully!");
                } else {
                    Log::info("✅ NVIDIA GPU conversion completed successfully!");
                }
            } else {
                Log::info("✅ CPU conversion completed successfully!");
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

        private static function createVideoFormat(int $bitrate, string $resolution, bool $isRetry): X264
    {
        $useGpu = config('hls.use_gpu_acceleration', false);

        if ($useGpu && !$isRetry) {
            $gpuType = self::detectBestGPU();

            if ($gpuType === 'nvidia') {
                Log::info("🔍 NVIDIA GPU detected, creating NVIDIA format...");
                return self::createNvidiaFormat($bitrate, $resolution);
            } elseif ($gpuType === 'apple') {
                Log::info("🔍 Apple Silicon detected, creating Apple format...");
                return self::createAppleFormat($bitrate, $resolution);
            } else {
                Log::warning('⚠️ GPU acceleration enabled but no compatible GPU found. Falling back to CPU.');
            }
        }

        Log::info("🔍 Creating CPU format...");
        return self::createCPUFormat($bitrate, $resolution);
    }

    private static function createNvidiaFormat(int $bitrate, string $resolution): X264
    {
        self::validateGPUConfig();
        $gpuDevice = config('hls.gpu_device', 'auto');
        $gpuPreset = config('hls.gpu_preset', 'fast');
        $gpuProfile = config('hls.gpu_profile', 'high');

        Log::info("🚀 Creating GPU format with:");
        Log::info("   - Device: {$gpuDevice}");
        Log::info("   - Preset: {$gpuPreset}");
        Log::info("   - Profile: {$gpuProfile}");
        Log::info("   - Bitrate: {$bitrate}k");
        Log::info("   - Resolution: {$resolution}");

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $additionalParams = [
            '-vf', 'scale='.self::renameResolution($resolution),
            '-c:v', 'h264_nvenc',
            '-c:a', 'aac',
            '-preset', $gpuPreset,
            '-profile:v', $gpuProfile,
            '-rc', 'cbr',
            '-b:v', $bitrate.'k',
            '-maxrate', $bitrate.'k',
            '-bufsize', ($bitrate * 2).'k',
        ];

        if ($gpuDevice !== 'auto') {
            $additionalParams[] = '-gpu_device';
            $additionalParams[] = $gpuDevice;
        }
        $format->setAdditionalParameters($additionalParams);

        Log::info("✅ NVIDIA GPU format created successfully");
        return $format;
    }

    private static function createAppleFormat(int $bitrate, string $resolution): X264
    {
        Log::info("🍎 Creating Apple Silicon format with:");
        Log::info("   - Bitrate: {$bitrate}k");
        Log::info("   - Resolution: {$resolution}");
        Log::info("   - Encoder: h264_videotoolbox");
        Log::info("   - Profile: main");

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $additionalParams = [
            '-vf', 'scale='.self::renameResolution($resolution),
            '-c:v', 'h264_videotoolbox',
            '-c:a', 'aac',
            '-profile:v', 'main',
            '-allow_sw', '1',  // Allow software fallback if needed
            '-b:v', $bitrate.'k',
            '-maxrate', $bitrate.'k',
            '-bufsize', ($bitrate * 2).'k',
        ];

        $format->setAdditionalParameters($additionalParams);

        Log::info("✅ Apple Silicon format created successfully");
        return $format;
    }

    private static function createCPUFormat(int $bitrate, string $resolution): X264
    {
        Log::info("🖥️ Creating CPU format with:");
        Log::info("   - Bitrate: {$bitrate}k");
        Log::info("   - Resolution: {$resolution}");
        Log::info("   - Preset: veryfast");
        Log::info("   - CRF: 22");

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $format->setAdditionalParameters([
            '-vf', 'scale='.self::renameResolution($resolution),
            '-preset', 'veryfast',
            '-crf', '22',
        ]);

        Log::info("✅ CPU format created successfully");
        return $format;
    }

        private static function detectBestGPU(): string
    {
        Log::info("🔍 Detecting best available GPU...");

        try {
            if (!function_exists('shell_exec')) {
                Log::warning('❌ GPU detection failed: shell_exec function not available.');
                return 'cpu';
            }

            self::$ffmpegPath = self::findBinary('ffmpeg');
            if (empty(self::$ffmpegPath)) {
                Log::warning('❌ GPU detection failed: ffmpeg binary not found.');
                return 'cpu';
            }

            Log::info("✅ FFmpeg found at: " . self::$ffmpegPath);

            $encoders = shell_exec(self::$ffmpegPath . ' -hide_banner -encoders 2>&1');

            // Check for Apple Silicon (VideoToolbox)
            if (str_contains($encoders, 'h264_videotoolbox')) {
                Log::info("🍎 Apple Silicon (VideoToolbox) detected!");
                return 'apple';
            }

            // Check for NVIDIA (NVENC)
            if (str_contains($encoders, 'h264_nvenc')) {
                Log::info("🚀 NVIDIA GPU (NVENC) detected!");

                // Check NVIDIA memory and temperature
                $memoryOK = self::hasSufficientGPUMemory();
                $tempOK = self::isGPUTempOK();

                if ($memoryOK && $tempOK) {
                    Log::info("✅ NVIDIA GPU is available and ready for use!");
                    return 'nvidia';
                } else {
                    Log::warning("❌ NVIDIA GPU check failed: Memory or temperature issues.");
                    return 'cpu';
                }
            }

            Log::warning("❌ No compatible GPU found. Using CPU.");
            return 'cpu';

        } catch (Exception $e) {
            Log::error('❌ GPU detection failed: ' . $e->getMessage());
            return 'cpu';
        }
    }

    private static function isGPUAvailable(): bool
    {
        $gpuType = self::detectBestGPU();
        return in_array($gpuType, ['nvidia', 'apple']);
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

    private static function isGPUFormat(X264 $format): bool
    {
        $params = $format->getAdditionalParameters();
        return (in_array('-c:v', $params) && in_array('h264_nvenc', $params)) ||
               (in_array('-c:v', $params) && in_array('h264_videotoolbox', $params));
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
