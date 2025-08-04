<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Services;

use AchyutN\LaravelHLS\Traits\DebugLoggable;
use Exception;
use Illuminate\Support\Facades\Storage;

final class EncryptionService
{
    use DebugLoggable;
    // Configuration constants
    private const ENCRYPTION_METHODS = [
        'AES_128' => 'aes-128',
        'ROTATING' => 'rotating',
        'NONE' => 'none'
    ];

    /**
     * Setup encryption for the export.
     */
    public function setupEncryption($export, object $state, array $videoInfo): void
    {
        if (config('hls.enable_encryption') && config('hls.encryption_method') !== self::ENCRYPTION_METHODS['NONE']) {
            $this->debugLog("🔐 Setting up HLS encryption...");
            $this->setupEncryptionMethod($export, $videoInfo['secretsDisk'], $state->outputFolder, $videoInfo['secretsOutputPath']);
        } else {
            $this->debugLog("🔓 Encryption disabled or set to 'none'");
        }
    }

    /**
     * Setup encryption based on the configured method.
     */
    private function setupEncryptionMethod($export, string $secretsDisk, string $outputFolder, string $secretsOutputPath): void
    {
        $encryptionMethod = config('hls.encryption_method', self::ENCRYPTION_METHODS['AES_128']);

        switch ($encryptionMethod) {
            case self::ENCRYPTION_METHODS['AES_128']:
                $this->setupStaticEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
                break;
            case self::ENCRYPTION_METHODS['ROTATING']:
                $this->setupRotatingEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
                break;
            case self::ENCRYPTION_METHODS['NONE']:
                $this->debugLog("🔓 Encryption disabled (none method selected)");
                break;
            default:
                $this->debugLog("⚠️ Unknown encryption method: {$encryptionMethod}, using static encryption", 'warning');
                $this->setupStaticEncryption($export, $secretsDisk, $outputFolder, $secretsOutputPath);
                break;
        }
    }

    /**
     * Setup static AES-128 encryption with a single key.
     */
    private function setupStaticEncryption($export, string $secretsDisk, string $outputFolder, string $secretsOutputPath): void
    {
        $this->debugLog("🔐 Setting up static AES-128 encryption...");

        // Generate a single encryption key
        $encryptionKey = \ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter::generateEncryptionKey();

        // Generate a unique key filename to avoid conflicts between videos
        $baseKeyFilename = config('hls.encryption_key_filename', 'secret.key');
        $keyFilename = $this->generateUniqueKeyFilename($baseKeyFilename, $outputFolder);

        // Store the key
        $keyPath = "{$outputFolder}/{$secretsOutputPath}/{$keyFilename}";
        Storage::disk($secretsDisk)->put($keyPath, $encryptionKey);

        $this->debugLog("🔑 Static encryption key stored at: {$keyPath}");

        // Apply encryption to the export
        $export->withEncryptionKey($encryptionKey, $keyFilename);
    }

    /**
     * Generate a unique key filename to avoid conflicts between videos.
     */
    private function generateUniqueKeyFilename(string $baseFilename, string $outputFolder): string
    {
        // Extract the name and extension from the base filename
        $pathInfo = pathinfo($baseFilename);
        $name = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        // Create a unique filename using the output folder (which is unique per video)
        // and a hash to ensure uniqueness
        $uniqueId = substr(md5($outputFolder), 0, 8);
        $uniqueFilename = "{$name}_{$uniqueId}{$extension}";

        $this->debugLog("🔑 Generated unique key filename: {$uniqueFilename} from base: {$baseFilename}");

        return $uniqueFilename;
    }

    /**
     * Setup rotating encryption with multiple keys.
     */
    private function setupRotatingEncryption($export, string $secretsDisk, string $outputFolder, string $secretsOutputPath): void
    {
        $this->debugLog("🔄 Setting up rotating encryption...");

        $segmentsPerKey = config('hls.rotating_key_segments', 1);
        $this->debugLog("🔄 Rotating key every {$segmentsPerKey} segment(s)");

        // Use rotating encryption with callback for key storage
        $export->withRotatingEncryptionKey(function ($filename, $contents) use ($secretsDisk, $outputFolder, $secretsOutputPath) {
            $fullPath = "{$outputFolder}/{$secretsOutputPath}/{$filename}";

            // Store the key file
            Storage::disk($secretsDisk)->put($fullPath, $contents);

            // If this is a key info file (.keyinfo), we need to ensure it has proper URI format
            if (str_ends_with($filename, '.keyinfo')) {
                $this->debugLog("🔑 Processing key info file: {$filename}");
                $this->fixKeyInfoFile($secretsDisk, $fullPath, $filename);
            }
        }, $segmentsPerKey);

        $this->debugLog("✅ Rotating encryption configured successfully");
    }

    /**
     * Fix the key info file to ensure it has proper URI format for FFmpeg.
     * FFmpeg expects the key info file to have a specific format with URI.
     */
    private function fixKeyInfoFile(string $disk, string $path, string $filename): void
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
                    $this->debugLog("🔧 Fixing key info file URI format for: {$filename}");

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
                    $this->debugLog("✅ Key info file fixed with proper URI: {$properUri}");
                }
            }
        } catch (Exception $e) {
            $this->debugLog("❌ Failed to fix key info file: " . $e->getMessage(), 'error');
        }
    }


}
