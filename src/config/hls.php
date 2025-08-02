<?php

declare(strict_types=1);

return [
    /**
     * Middlewares the HLS routes should use.
     * This should be an array of middleware names that will be applied
     * to the HLS routes.
     *
     * Default: []
     */
    'middlewares' => [
        // 'auth', // Uncomment to enable authentication middleware
    ],

    /**
     * The queue name for HLS conversion jobs.
     * This should be a string that defines the queue
     * name where the HLS conversion jobs will be dispatched.
     *
     * Default: "default"
     */
    'queue_name' => 'default',

    /**
     * This determines whether the HLS output files should be encrypted
     * using AES-128 encryption.
     *
     * Default: true
     */
    'enable_encryption' => true,

    /**
     * The encryption method to use for HLS encryption.
     * Options: 'aes-128', 'rotating', 'none'
     *
     * - 'aes-128': Uses a single static key for all segments
     * - 'rotating': Uses different keys for different segments (more secure)
     * - 'none': Disables encryption entirely
     *
     * Note: If you experience issues with encryption, you can set this to 'none'
     * to disable encryption entirely.
     *
     * Default: 'aes-128'
     */
    'encryption_method' => 'aes-128',

    /**
     * The number of segments that use the same key when using rotating encryption.
     * This only applies when 'encryption_method' is set to 'rotating'.
     *
     * Default: 1
     */
    'rotating_key_segments' => 1,

    /**
     * The filename for the encryption key when using static encryption.
     * This only applies when 'encryption_method' is set to 'aes-128'.
     *
     * Default: 'secret.key'
     */
    'encryption_key_filename' => 'secret.key',

    /**
     * The bitrates for different resolutions. (In kbps)
     * This should be an associative array where the keys are
     * resolution strings in the format '{resolution}'
     * and the values are the corresponding bitrates in kbps.
     */
    'bitrates' => [
        '360p' => 600,
        '480p' => 1000,
        '720p' => 2500,
        '1080p' => 4500,
        '1440p' => 7000,
        '2160p' => 12000,
    ],

    /**
     * The resolutions for HLS conversion.
     * This should be an associative array where the keys are
     * resolution strings in the format '{resolution}'
     * and the values are the corresponding resolution strings
     * in the format '{width}x{height}'.
     *
     * Example: ['480p' => '854x480', '720p' => '1280x720', '1080p' => '1920x1080']
     */
    'resolutions' => [
        '360p' => '640x360',
        '480p' => '854x480',
        '720p' => '1280x720',
        '1080p' => '1920x1080',
        '1440p' => '2560x1440',
        '2160p' => '3840x2160',
    ],

    /**
     * The database column that is used to store the original video path.
     * This should be a string column that contains the path to the original
     * video file in default storage.
     *
     * Default: 'video_path'
     */
    'video_column' => 'video_path',

    /**
     * The database column that is used to store the HLS folder.
     * This should be a string column that contains the path to the HLS
     * output folder in default storage.
     *
     * Default: 'hls_path'
     */
    'hls_column' => 'hls_path',

    /**
     * The database column that is used to store the conversion progress.
     * This should be an integer column that contains the progress percentage
     * of the HLS conversion.
     *
     * Default: 'conversion_progress'
     */
    'progress_column' => 'conversion_progress',

    /**
     * The disk where the original video files are stored.
     * This should be a valid disk name as defined in your
     * `config/filesystems.php` file.
     *
     * Default: 'public'
     */
    'video_disk' => 'public',

    /**
     * The disk where the HLS output files are stored.
     * This should be a valid disk name as defined in your
     * `config/filesystems.php` file.
     *
     * Default: 'local'
     */
    'hls_disk' => 'local',

    /**
     * The disk where the encryption secrets are stored.
     * This should be a valid disk name as defined in your
     * `config/filesystems.php` file.
     *
     * Default: 'local'
     */
    'secrets_disk' => 'local',

    /**
     * The path where the HLS output files will be stored.
     * This should be a valid path relative to the disk defined
     * in `hls_disk`.
     *
     * Default: 'hls'
     */
    'hls_output_path' => 'hls',

    /**
     * The path where the encryption secrets will be stored.
     * This should be a valid path relative to the disk defined
     * in `secret_disk`.
     *
     * Default: 'hls/secrets'
     */
    'secrets_output_path' => 'secrets',

    /**
     * The path where the conversion temp files are stored.
     *
     * Default: 'tmp'
     */
    'temp_storage_path' => 'tmp',

    /**
     * The path where the conversion temp files are stored.
     *
     * Default: 'tmp'
     */
    'temp_hls_storage_path' => 'tmp',

    /**
     * The model aliases to detect the class for conversion.
     * This should be an array of model class names that
     * implement the ConvertsToHLS trait.
     *
     * Default: []
     */
    'model_aliases' => [
        //        'video' => \App\Models\Video::class,
    ],

    /**
     * This determines whether the HLS routes should be registered.
     * If set to true, the package will register the necessary routes
     * for HLS conversion and playback.
     *
     * Default: true
     */
    'register_routes' => true,

    /**
     * This determines whether the original video file should be deleted
     * after the HLS conversion is complete.
     *
     * Default: false
     */
    'delete_original_file_after_conversion' => false,

    /**
     * This determines whether to use NVIDIA GPU acceleration for video encoding.
     * When enabled, the conversion will use NVENC encoder for faster processing.
     * Requires NVIDIA GPU with NVENC support and proper drivers installed.
     *
     * Default: false
     */
    'use_gpu_acceleration' => false,

    /**
     * The NVIDIA GPU device to use for encoding.
     * This should be the GPU index (0, 1, 2, etc.) or 'auto' for automatic selection.
     * Only used when 'use_gpu_acceleration' is true.
     *
     * Default: 'auto'
     */
    'gpu_device' => 'auto',

    /**
     * The NVIDIA encoder preset to use for GPU acceleration.
     * Options: 'fast', 'medium', 'slow', 'hq', 'll', 'llhq', 'lossless', 'losslesshq'
     * Only used when 'use_gpu_acceleration' is true.
     *
     * Default: 'fast'
     */
    'gpu_preset' => 'fast',

    /**
     * The NVIDIA encoder profile to use for GPU acceleration.
     * Options: 'baseline', 'main', 'high'
     * Only used when 'use_gpu_acceleration' is true.
     *
     * Default: 'high'
     */
    'gpu_profile' => 'high',

    /**
     * This determines whether to enable debug logging for HLS conversion.
     * When enabled, detailed logs will be output during the conversion process.
     * This can be useful for troubleshooting but may impact performance.
     *
     * Default: false
     */
    'debug' => false,

];
