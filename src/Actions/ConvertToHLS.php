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
     * Log a message only if debug mode is enabled.
     */
    private static function debugLog(string $message, string $level = 'info'): void
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

    /**
     * Convert a video file to HLS format.
     */
    public static function convertToHLS(string $inputPath, string $outputFolder, Model $model, bool $isRetry = false): void
    {
        $startTime = microtime(true);
        $wasGpuUsed = false;
        $gpuType = null;  // ✅ Initialize to null
        $useGpu = config('hls.use_gpu_acceleration', false);

        self::debugLog("Starting HLS conversion for: {$inputPath}");
        self::debugLog("GPU acceleration enabled: " . ($useGpu ? 'YES' : 'NO'));

        if ($isRetry) {
            self::debugLog("This is a retry attempt using CPU fallback");
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

            self::debugLog("📊 Processing " . count($lowerResolutions) . " resolutions: " . implode(', ', array_keys($lowerResolutions)));
            self::debugLog("📹 Original video resolution: {$fileResolution}, bitrate: {$fileBitrate}k");

            foreach ($lowerResolutions as $resolution => $res) {
                $bitrate = $kiloBitRates[$resolution] ?? 1000;
                self::debugLog("🎬 Creating format for resolution: {$resolution} with bitrate: {$bitrate}k");

                $format = self::createVideoFormat((int) $bitrate, $res, $isRetry);
                $formats[] = $format;

                // ✅ Store GPU type when first detected
                if ($useGpu && !$isRetry && self::isGPUFormat($format)) {
                    $wasGpuUsed = true;
                    if ($gpuType === null) {  // Only detect once
                        $gpuType = self::detectBestGPU();
                    }
                    if ($gpuType === 'apple') {
                        self::debugLog("🍎 Apple Silicon format created for resolution: {$resolution}");
                    } else {
                        self::debugLog("🚀 NVIDIA GPU format created for resolution: {$resolution}");
                    }
                } else {
                    self::debugLog("🖥️ CPU format created for resolution: {$resolution}");
                }
            }

            if (empty($formats)) {
                self::debugLog("⚠️ No resolutions found, using original video format");
                $format = self::createVideoFormat((int) $fileBitrate, $fileResolution, $isRetry);
                $formats[] = $format;
                if ($useGpu && !$isRetry && self::isGPUFormat($format)) {
                    $wasGpuUsed = true;
                    if ($gpuType === null) {  // ✅ Only detect once
                        $gpuType = self::detectBestGPU();
                    }
                    if ($gpuType === 'apple') {
                        self::debugLog("🍎 Apple Silicon format created for original resolution");
                    } else {
                        self::debugLog("🚀 NVIDIA GPU format created for original resolution");
                    }
                } else {
                    self::debugLog("🖥️ CPU format created for original resolution");
                }
            }

            $export = FFMpeg::fromDisk($videoDisk)->open($inputPath)->exportForHLS()->toDisk($hlsDisk);
            foreach ($formats as $format) {
                $export->addFormat($format);
            }

            self::debugLog("🔄 Starting conversion process...");
            self::debugLog("🎯 Target: {$outputFolder}/{$hlsOutputPath}/playlist.m3u8");
            info('Started conversion for resolutions: '.implode(', ', array_keys($lowerResolutions)));

            $progress = progress(label: 'Converting video to HLS format...', steps: 100);
            $progress->start();
            $export->onProgress(function ($percentage) use ($model, $progress, $startTime): void {
                $progress->hint(self::estimateTime($startTime, $percentage));
                $progress->advance();
                UpdateConversionProgress::dispatch($model, $percentage);
            });

            if (config('hls.enable_encryption') && config('hls.encryption_method') !== 'none') {
                self::debugLog("🔐 Setting up HLS encryption...");
                self::setupEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
            } else {
                self::debugLog("🔓 Encryption disabled or set to 'none'");
            }

            $export->save("{$outputFolder}/{$hlsOutputPath}/playlist.m3u8");
            $progress->finish();

            if ($wasGpuUsed) {
                self::logGPUPerformance($startTime);
                // ✅ Use stored GPU type instead of detecting again
                if ($gpuType === 'apple') {
                    self::debugLog("✅ Apple Silicon conversion completed successfully!");
                } else {
                    self::debugLog("✅ NVIDIA GPU conversion completed successfully!");
                }
            } else {
                self::debugLog("✅ CPU conversion completed successfully!");
            }

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Check if this is an encryption-related error
            if (str_contains($errorMessage, 'no key URI specified') ||
                str_contains($errorMessage, 'Invalid argument') ||
                str_contains($errorMessage, 'key info file')) {

                self::debugLog("🔐 Encryption error detected: {$errorMessage}", 'warning');
                self::debugLog("🔄 Retrying without encryption...", 'warning');

                // Retry without encryption
                self::convertToHLSWithoutEncryption($inputPath, $outputFolder, $model);
                return;
            }

            if ($wasGpuUsed) {
                self::debugLog('GPU conversion failed, falling back to CPU: ' . $errorMessage, 'warning');
                self::convertToHLSWithCPU($inputPath, $outputFolder, $model);
            } else {
                throw new Exception("HLS conversion failed: {$errorMessage}");
            }
        } finally {
            FFMpeg::cleanupTemporaryFiles();
        }
    }

    private static function convertToHLSWithCPU(string $inputPath, string $outputFolder, Model $model): void
    {
        self::debugLog("Retrying conversion for {$inputPath} using CPU.");
        self::convertToHLS($inputPath, $outputFolder, $model, true);
    }

    private static function convertToHLSWithoutEncryption(string $inputPath, string $outputFolder, Model $model): void
    {
        self::debugLog("Retrying conversion for {$inputPath} without encryption.");

        // Temporarily disable encryption for this conversion
        $originalEncryption = config('hls.enable_encryption');
        config(['hls.enable_encryption' => false]);

        try {
            self::convertToHLS($inputPath, $outputFolder, $model, false);
        } finally {
            // Restore original encryption setting
            config(['hls.enable_encryption' => $originalEncryption]);
        }
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
                self::debugLog("🔍 NVIDIA GPU detected, creating NVIDIA format...");
                return self::createNvidiaFormat($bitrate, $resolution);
            } elseif ($gpuType === 'apple') {
                self::debugLog("🔍 Apple Silicon detected, creating Apple format...");
                return self::createAppleFormat($bitrate, $resolution);
            } else {
                self::debugLog('⚠️ GPU acceleration enabled but no compatible GPU found. Falling back to CPU.', 'warning');
            }
        }

        self::debugLog("🔍 Creating CPU format...");
        return self::createCPUFormat($bitrate, $resolution);
    }

    private static function createNvidiaFormat(int $bitrate, string $resolution): X264
    {
        self::validateGPUConfig();
        $gpuDevice = config('hls.gpu_device', 'auto');
        $gpuPreset = config('hls.gpu_preset', 'fast');
        $gpuProfile = config('hls.gpu_profile', 'high');

        self::debugLog("🚀 Creating GPU format with:");
        self::debugLog("   - Device: {$gpuDevice}");
        self::debugLog("   - Preset: {$gpuPreset}");
        self::debugLog("   - Profile: {$gpuProfile}");
        self::debugLog("   - Bitrate: {$bitrate}k");
        self::debugLog("   - Resolution: {$resolution}");

        // Use default X264 constructor but override with additional parameters
        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $additionalParams = [
            '-vf', 'scale='.self::renameResolution($resolution),
            '-c:v', 'h264_nvenc',  // Override the video codec
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

        self::debugLog("✅ NVIDIA GPU format created successfully");
        return $format;
    }

    private static function createAppleFormat(int $bitrate, string $resolution): X264
    {
        self::debugLog("🍎 Creating Apple Silicon format with:");
        self::debugLog("   - Bitrate: {$bitrate}k");
        self::debugLog("   - Resolution: {$resolution}");
        self::debugLog("   - Encoder: h264_videotoolbox");
        self::debugLog("   - Quality: medium");
        self::debugLog("   - Realtime: true");

        // Use default X264 constructor (which sets libx264) but override with additional parameters
        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $additionalParams = [
            '-vf', 'scale='.self::renameResolution($resolution),
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

        self::debugLog("✅ Apple Silicon format created successfully");
        return $format;
    }

    private static function createCPUFormat(int $bitrate, string $resolution): X264
    {
        self::debugLog("🖥️ Creating CPU format with:");
        self::debugLog("   - Bitrate: {$bitrate}k");
        self::debugLog("   - Resolution: {$resolution}");
        self::debugLog("   - Preset: veryfast");
        self::debugLog("   - CRF: 22");

        $format = new X264();
        $format->setKiloBitrate($bitrate);
        $format->setAudioKiloBitrate(128);
        $format->setAdditionalParameters([
            '-vf', 'scale='.self::renameResolution($resolution),
            '-preset', 'veryfast',
        ]);

        self::debugLog("✅ CPU format created successfully");
        return $format;
    }

    private static function detectBestGPU(): string
    {
        self::debugLog("🔍 Detecting best available GPU...");

        try {
            if (!function_exists('shell_exec')) {
                self::debugLog('❌ GPU detection failed: shell_exec function not available.', 'warning');
                return 'cpu';
            }

            self::$ffmpegPath = self::findBinary('ffmpeg');
            if (empty(self::$ffmpegPath)) {
                self::debugLog('❌ GPU detection failed: ffmpeg binary not found.', 'warning');
                return 'cpu';
            }

            self::debugLog("✅ FFmpeg found at: " . self::$ffmpegPath);

            $encoders = shell_exec(self::$ffmpegPath . ' -hide_banner -encoders 2>&1');

            // Check for Apple Silicon (VideoToolbox)
            if (str_contains($encoders, 'h264_videotoolbox')) {
                self::debugLog("🍎 Apple Silicon (VideoToolbox) detected!");
                return 'apple';
            }

            // Check for NVIDIA (NVENC)
            if (str_contains($encoders, 'h264_nvenc')) {
                self::debugLog("🚀 NVIDIA GPU (NVENC) detected!");

                // Check NVIDIA memory and temperature
                $memoryOK = self::hasSufficientGPUMemory();
                $tempOK = self::isGPUTempOK();

                if ($memoryOK && $tempOK) {
                    self::debugLog("✅ NVIDIA GPU is available and ready for use!");
                    return 'nvidia';
                } else {
                    self::debugLog("❌ NVIDIA GPU check failed: Memory or temperature issues.", 'warning');
                    return 'cpu';
                }
            }

            self::debugLog("❌ No compatible GPU found. Using CPU.", 'warning');
            return 'cpu';

        } catch (Exception $e) {
            self::debugLog('❌ GPU detection failed: ' . $e->getMessage(), 'error');
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
            self::debugLog("GPU check failed: Insufficient free memory. Found: {$freeMemory}MB, Required: {$minRequiredMemory}MB.", 'warning');
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
            self::debugLog("GPU check failed: Temperature too high. Current: {$temp}°C, Threshold: {$maxTemp}°C.", 'warning');
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
            self::debugLog("Invalid GPU preset '{$preset}', using 'fast' instead.", 'warning');
            config(['hls.gpu_preset' => 'fast']);
        }
        $profile = config('hls.gpu_profile', 'high');
        $validProfiles = ['baseline', 'main', 'high'];
        if (!in_array($profile, $validProfiles)) {
            self::debugLog("Invalid GPU profile '{$profile}', using 'high' instead.", 'warning');
            config(['hls.gpu_profile' => 'high']);
        }
    }

    private static function logGPUPerformance(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        self::debugLog("GPU conversion completed in " . round($duration, 2) . " seconds.");
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

    /**
     * Fix the key info file to ensure it has proper URI format for FFmpeg.
     * FFmpeg expects the key info file to have a specific format with URI.
     */
    private static function fixKeyInfoFile(string $disk, string $path, string $filename): void
    {
        try {
            $contents = Storage::disk($disk)->get($path);
            $lines = explode("\n", trim($contents));

            // FFmpeg expects key info file format:
            // URI
            // path_to_key_file
            // IV (optional)

            if (count($lines) >= 2) {
                $uri = trim($lines[0]);
                $keyPath = trim($lines[1]);

                // If URI is empty or doesn't look like a proper URI, we need to fix it
                if (empty($uri) || !filter_var($uri, FILTER_VALIDATE_URL)) {
                    self::debugLog("🔧 Fixing key info file URI format for: {$filename}");

                    // Create a simple but valid URI that points to the key file
                    // This is a fallback approach that should work with most setups
                    $keyName = str_replace('.keyinfo', '.key', $filename);

                    // Use a relative path approach that should work with the existing route structure
                    // The actual URI will be resolved by the HLS service when serving the playlist
                    $properUri = "/hls/keys/{$keyName}";

                    // Reconstruct the key info file with proper URI
                    $newContents = $properUri . "\n" . $keyPath;
                    if (count($lines) > 2) {
                        $newContents .= "\n" . $lines[2]; // Keep IV if present
                    }

                    Storage::disk($disk)->put($path, $newContents);
                    self::debugLog("✅ Key info file fixed with proper URI: {$properUri}");
                }
            }
        } catch (Exception $e) {
            self::debugLog("❌ Failed to fix key info file: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Setup encryption based on the configured method.
     */
    private static function setupEncryption($export, string $secretsDisk, string $outputFolder, string $secretsOutputPath): void
    {
        $encryptionMethod = config('hls.encryption_method', 'aes-128');

        switch ($encryptionMethod) {
            case 'aes-128':
                self::setupStaticEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
                break;
            case 'rotating':
                self::setupRotatingEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
                break;
            default:
                self::debugLog("⚠️ Unknown encryption method: {$encryptionMethod}, using static encryption", 'warning');
                self::setupStaticEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
                break;
        }
    }

    /**
     * Setup static AES-128 encryption with a single key.
     */
    private static function setupStaticEncryption($export, string $secretsDisk, string $outputFolder, string $secretsOutputPath): void
    {
        self::debugLog("🔐 Setting up static AES-128 encryption...");

        // Generate a single encryption key
        $encryptionKey = \ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter::generateEncryptionKey();

        // Generate a unique key filename to avoid conflicts between videos
        $baseKeyFilename = config('hls.encryption_key_filename', 'secret.key');
        $keyFilename = self::generateUniqueKeyFilename($baseKeyFilename, $outputFolder);

        // Store the key
        $keyPath = "{$outputFolder}/{$secretsOutputPath}/{$keyFilename}";
        Storage::disk($secretsDisk)->put($keyPath, $encryptionKey);

        self::debugLog("🔑 Static encryption key stored at: {$keyPath}");

        // Apply encryption to the export
        $export->withEncryptionKey($encryptionKey, $keyFilename);
    }

    /**
     * Generate a unique key filename to avoid conflicts between videos.
     */
    private static function generateUniqueKeyFilename(string $baseFilename, string $outputFolder): string
    {
        // Extract the name and extension from the base filename
        $pathInfo = pathinfo($baseFilename);
        $name = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        // Create a unique filename using the output folder (which is unique per video)
        // and a hash to ensure uniqueness
        $uniqueId = substr(md5($outputFolder), 0, 8);
        $uniqueFilename = "{$name}_{$uniqueId}{$extension}";

        self::debugLog("🔑 Generated unique key filename: {$uniqueFilename} from base: {$baseFilename}");

        return $uniqueFilename;
    }

    /**
     * Setup rotating encryption with multiple keys.
     */
    private static function setupRotatingEncryption($export, string $secretsDisk, string $outputFolder, string $secretsOutputPath): void
    {
        self::debugLog("🔄 Setting up rotating encryption...");

        $segmentsPerKey = config('hls.rotating_key_segments', 1);
        self::debugLog("🔄 Rotating key every {$segmentsPerKey} segment(s)");

        // Use rotating encryption with callback for key storage
        $export->withRotatingEncryptionKey(function ($filename, $contents) use ($secretsDisk, $outputFolder, $secretsOutputPath) {
            $fullPath = "{$outputFolder}/{$secretsOutputPath}/{$filename}";

            // Store the key file
            Storage::disk($secretsDisk)->put($fullPath, $contents);

            // If this is a key info file (.keyinfo), we need to ensure it has proper URI format
            if (str_ends_with($filename, '.keyinfo')) {
                self::debugLog("🔑 Processing key info file: {$filename}");
                self::fixKeyInfoFile($secretsDisk, $fullPath, $filename);
            }
        }, $segmentsPerKey);

        self::debugLog("✅ Rotating encryption configured successfully");
    }
}
