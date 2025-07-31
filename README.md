# Laravel HLS

[![Laravel HLS](https://banners.beyondco.de/Laravel%20HLS.png?theme=light&packageManager=composer+require&packageName=achyutn%2Flaravel-hls&pattern=anchorsAway&style=style_1&description=A+package+to+convert+video+files+to+HLS+with+rotating+key+encryption.&md=1&showWatermark=0&fontSize=150px&images=video-camera "Laravel HLS")](https://packagist.org/packages/achyutn/laravel-hls)

![Packagist Version](https://img.shields.io/packagist/v/achyutn/laravel-hls?label=Latest%20Version)
![Packagist Downloads](https://img.shields.io/packagist/dt/achyutn/laravel-hls?label=Packagist%20Downloads)
![Packagist Stars](https://img.shields.io/packagist/stars/achyutn/laravel-hls?label=Stars)
[![Run Test for Pull Request](https://github.com/achyutkneupane/laravel-hls/actions/workflows/master.yml/badge.svg)](https://github.com/achyutkneupane/laravel-hls/actions/workflows/master.yml)
[![Bump version](https://github.com/achyutkneupane/laravel-hls/actions/workflows/tagrelease.yml/badge.svg)](https://github.com/achyutkneupane/laravel-hls/actions/workflows/tagrelease.yml)

`laravel-hls` is a Laravel package for converting video files into adaptive HLS (HTTP Live Streaming) streams using
`ffmpeg`, with built-in AES-128 encryption, queue support, and model-based configuration.

This package makes use of the [laravel-ffmpeg](https://github.com/protonemedia/laravel-ffmpeg) package to handle video
processing and conversion to HLS format. It provides a simple way to convert video files stored in your Laravel
application into HLS streams, which can be used for adaptive bitrate streaming.

**Features:**
- 🚀 **Advanced GPU Acceleration**: Full NVIDIA GPU acceleration with NVENC encoder, automatic fallback, and health monitoring
- 💻 **Intelligent CPU Fallback**: Automatic fallback to CPU encoding when GPU is unavailable or fails
- 🔒 **AES-128 Encryption**: Built-in encryption for secure video streaming
- 📊 **Real-time Progress Tracking**: Live conversion progress monitoring with ETA
- 🎯 **Adaptive Bitrate**: Multiple resolution and bitrate support for optimal streaming
- 🔍 **Comprehensive Monitoring**: GPU performance, memory, and temperature monitoring
- 🛡️ **Robust Error Handling**: Graceful error handling with detailed logging
- 🌐 **Cross-platform Support**: Works on Linux, macOS, and Windows

## Installation

You can install the package via Composer:

```bash
composer require achyutn/laravel-hls
```

You must publish the [configuration file](src/config/hls.php) using the following command:

```bash
php artisan vendor:publish --provider="AchyutN\LaravelHLS\HLSProvider" --tag="hls-config"
```

The configuration file is required to set-up the aliases for the models that will use the HLS conversion trait.

```php
<?php

return [
    // Other configs in hls.php

    'model_aliases' => [
        'video' => \App\Models\Video::class,
    ],
];
```

## Usage

You just need to add the `ConvertsToHls` trait to your model. The package will automatically handle the conversion of
your video files to HLS format.

```php
<?php

namespace App\Models;

use AchyutN\LaravelHLS\Traits\ConvertsToHls;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use ConvertsToHls;
}
```

### HLS playlist

To fetch the HLS playlist for a video, you can call the endpoint `/hls/{model}/{id}/playlist` or
`route('hls.playlist', ['model' => 'video', 'id' => $id])` where `$model` is an instance of your
model that uses the `ConvertsToHls` trait and `$id` is the ID of the model you want to fetch the
playlist for. This will return the HLS playlist in `m3u8` format.

```php
use App\Models\Video;

// Fetch the HLS playlist for a video
$video = Video::findOrFail($id);
$playlistUrl = route('hls.playlist', ['model' => 'video', 'id' => $video->id]);
```

### Registering Routes

By default, the package registers the HLS playlist routes automatically. If you want to disable this behavior, you can
set the `register_routes` option to `false` in your `config/hls.php` file. And to create your own routes, you can use the
[HLSService](src/Services/HLSService.php) class to generate the HLS playlist URL.

```php
use AchyutN\LaravelHLS\HLSService;

class CustomHLSController
{
    public function __construct(private HLSService $hlsService) {}

    public function stream(Video $video)
    {
        return $this->hlsService->getPlaylist('video', $video->id);
    }
}
```

## Configuration

### Global Configuration

You can configure the package by editing the `config/hls.php` file. Below are the available options:

| Key                                     | Description                                                                                    | Type     | Default               |
|-----------------------------------------|------------------------------------------------------------------------------------------------|----------|-----------------------|
| `middlewares`                           | Middleware applied to HLS playlist routes.                                                     | `array`  | `[]`                  |
| `queue_name`                            | The name of the queue used for HLS conversion jobs.                                            | `string` | `default`             |
| `enable_encryption`                     | Whether to enable AES-128 encryption for HLS segments.                                         | `bool`   | `true`                |
| `bitrates`                              | An array of bitrates for HLS conversion.                                                       | `array`  | *See config file*     |
| `resolutions`                           | An array of resolutions for HLS conversion.                                                    | `array`  | *See config file*     |
| `video_column`                          | The database column that stores the original video path.                                       | `string` | `video_path`          |
| `hls_column`                            | The database column that stores the path to the HLS output folder.                             | `string` | `hls_path`            |
| `progress_column`                       | The database column that stores the conversion progress percentage.                            | `string` | `conversion_progress` |
| `video_disk`                            | The filesystem disk where original video files are stored. Refer to `config/filesystems.php`.  | `string` | `public`              |
| `hls_disk`                              | The filesystem disk where HLS output files are stored. Refer to `config/filesystems.php`.      | `string` | `local`               |
| `secrets_disk`                          | The filesystem disk where encryption secrets are stored.                                       | `string` | `local`               |
| `hls_output_path`                       | Path relative to `hls_disk` where HLS files are saved.                                         | `string` | `hls`                 |
| `secrets_output_path`                   | Path relative to `secrets_disk` where encryption secrets are saved.                            | `string` | `secrets`             |
| `temp_storage_path`                     | Specify where the conversion tmp files are saved.                                              | `string` | `tmp`                 |
| `model_aliases`                         | An array of model aliases for easy access to HLS conversion.                                   | `array`  | `[]`                  |
| `register_routes`                       | Whether to register the HLS playlist routes automatically.                                     | `bool`   | `true`                |
| `delete_original_file_after_conversion` | A bool to turn on/off deleting the original video after conversion.                            | `bool`   | `false`               |
| `use_gpu_acceleration`                  | Whether to use NVIDIA GPU acceleration for video encoding.                                     | `bool`   | `false`               |
| `gpu_device`                            | The NVIDIA GPU device to use (0, 1, 2, etc.) or 'auto' for automatic selection.              | `string` | `auto`                |
| `gpu_preset`                            | The NVIDIA encoder preset (fast, medium, slow, hq, ll, llhq, lossless, losslesshq).           | `string` | `fast`                |
| `gpu_profile`                           | The NVIDIA encoder profile (baseline, main, high).                                            | `string` | `high`                |
| `gpu_min_memory_mb`                    | Minimum required GPU memory in MB for conversion.                                             | `int`    | `500`                 |
| `gpu_max_temp`                          | Maximum GPU temperature in Celsius before falling back to CPU.                                | `int`    | `85`                  |

> 💡 Tip: All disk values must be valid disks defined in your `config/filesystems.php`.

### Advanced GPU Acceleration Configuration

The package now includes comprehensive NVIDIA GPU acceleration with intelligent fallback and health monitoring. To enable GPU acceleration, you need:

1. **NVIDIA GPU** with NVENC support (GTX 600 series or newer)
2. **NVIDIA drivers** installed on your system
3. **FFmpeg** compiled with NVENC support

#### Enabling GPU Acceleration

```php
// In config/hls.php
'use_gpu_acceleration' => true,
'gpu_device' => 'auto',        // or specific GPU index like '0', '1'
'gpu_preset' => 'fast',        // fast, medium, slow, hq, ll, llhq, lossless, losslesshq
'gpu_profile' => 'high',       // baseline, main, high
'gpu_min_memory_mb' => 500,    // Minimum GPU memory required
'gpu_max_temp' => 85,          // Maximum GPU temperature before fallback
```

#### GPU Configuration Options

| Option | Description | Default | Valid Values |
|--------|-------------|---------|--------------|
| `use_gpu_acceleration` | Enable/disable GPU acceleration | `false` | `true`, `false` |
| `gpu_device` | GPU device index or 'auto' | `auto` | `auto`, `0`, `1`, `2`, etc. |
| `gpu_preset` | Quality/speed balance | `fast` | `fast`, `medium`, `slow`, `hq`, `ll`, `llhq`, `lossless`, `losslesshq` |
| `gpu_profile` | H.264 profile for compatibility | `high` | `baseline`, `main`, `high` |
| `gpu_min_memory_mb` | Minimum GPU memory required | `500` | Any positive integer |
| `gpu_max_temp` | Maximum GPU temperature | `85` | Any positive integer |

#### Intelligent Fallback System

The package now includes a sophisticated fallback system:

- **Automatic Detection**: Checks for GPU availability, memory, and temperature
- **Graceful Fallback**: Automatically switches to CPU encoding if GPU fails
- **Performance Monitoring**: Logs GPU performance metrics for optimization
- **Error Recovery**: Handles GPU failures gracefully without stopping the conversion

#### GPU Health Monitoring

The package monitors several GPU health metrics:

- **Memory Usage**: Ensures sufficient GPU memory is available
- **Temperature**: Prevents overheating by monitoring GPU temperature
- **Encoder Support**: Verifies NVENC encoder availability
- **Performance Tracking**: Logs conversion time and performance metrics

#### Checking GPU Availability

You can check if your system supports GPU acceleration by running:

```bash
ffmpeg -hide_banner -encoders | grep h264_nvenc
```

If this command returns output containing `h264_nvenc`, your system supports GPU acceleration.

#### Advanced GPU Monitoring

The package automatically logs GPU performance metrics:

```php
// GPU performance is automatically logged when GPU acceleration is used
Log::info("GPU conversion completed in 45.23 seconds.");
Log::warning("GPU memory usage: 2048MB / 8192MB (25.0%)");
Log::warning("GPU temperature: 72°C (within limits)");
```

#### Troubleshooting GPU Issues

- **"GPU acceleration is enabled but NVIDIA GPU with NVENC support is not available"**: Ensure you have NVIDIA drivers installed and FFmpeg compiled with NVENC support
- **"GPU check failed: Insufficient free memory"**: Increase `gpu_min_memory_mb` or close other GPU-intensive applications
- **"GPU check failed: Temperature too high"**: Increase `gpu_max_temp` or improve GPU cooling
- **Poor performance**: Try different presets (`fast`, `medium`, `slow`) to find the best balance for your use case
- **Compatibility issues**: Use `baseline` or `main` profile instead of `high` for broader device compatibility

#### Cross-Platform Support

The GPU acceleration works across different operating systems:

- **Linux**: Full support with automatic binary detection
- **Windows**: Support for Windows with proper path detection
- **macOS**: Support for macOS systems

### Model-Level Configuration

You can override any global setting on a **per-model basis** by defining public properties in your Eloquent model. These
override values will be used instead of the global config.

| Property                 | Description                                                                       | Type     |
|--------------------------|-----------------------------------------------------------------------------------|----------|
| `$videoColumn`           | Overrides `video_column` from config. Path to the original video file.            | `string` |
| `$hlsColumn`             | Overrides `hls_column`. Path to the generated HLS folder.                         | `string` |
| `$progressColumn`        | Overrides `progress_column`. Stores HLS conversion progress.                      | `string` |
| `$videoDisk`             | Overrides `video_disk`. Disk name for the original video.                         | `string` |
| `$hlsDisk`               | Overrides `hls_disk`. Disk name for the HLS output.                               | `string` |
| `$secretsDisk`           | Overrides `secrets_disk`. Disk for storing encryption keys.                       | `string` |
| `$hlsOutputPath`         | Overrides `hls_output_path`. Path to store HLS files relative to `hlsDisk`.       | `string` |
| `$hlsSecretsOutputPath`  | Overrides `secrets_output_path`. Path to store secrets relative to `secretsDisk`. | `string` |
| `$tempStorageOutputPath` | Overrides `temp_storage_path`. Path to store conversion temp files to `tmp`.      | `string` |

#### Example

```php
use AchyutN\LaravelHLS\Traits\ConvertsToHls;

class CustomVideo extends Model
{
    use ConvertsToHls;

    public string $videoColumn = 'original_video';
    public string $hlsColumn = 'hls_output';
    public string $progressColumn = 'conversion_percent';

    public string $videoDisk = 'videos';
    public string $hlsDisk = 'hls-outputs';
    public string $secretsDisk = 'secure';

    public string $hlsOutputPath = 'streamed/hls';
    public string $hlsSecretsOutputPath = 'streamed/secrets';
    
    public string $tempStorageOutputPath = 'tmp';
}
```

## Performance Optimization

### GPU vs CPU Performance

The package automatically optimizes performance based on your hardware:

- **GPU Acceleration**: 3-5x faster conversion for supported hardware
- **CPU Fallback**: Reliable conversion when GPU is unavailable
- **Memory Management**: Prevents out-of-memory errors
- **Temperature Monitoring**: Protects hardware from overheating

### Monitoring and Logging

The package provides comprehensive logging for monitoring and debugging:

```php
// GPU performance logging
Log::info("GPU conversion completed in 45.23 seconds.");

// GPU health monitoring
Log::warning("GPU memory usage: 2048MB / 8192MB (25.0%)");
Log::warning("GPU temperature: 72°C (within limits)");

// Fallback logging
Log::warning("GPU acceleration enabled but GPU not available. Falling back to CPU.");
Log::warning("GPU conversion failed, falling back to CPU: [error message]");
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Changelog

See the [CHANGELOG](CHANGELOG.md) for details on changes made in each version.

## Contributing

Contributions are welcome! Please create a pull request or open an issue if you find any bugs or have feature requests.

## Support

If you find this package useful, please consider starring the repository on GitHub to show your support.
