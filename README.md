# Laravel HLS

[![Laravel HLS](https://banners.beyondco.de/Laravel%20HLS.png?theme=light&packageManager=composer+require&packageName=achyutn%2Flaravel-hls&pattern=anchorsAway&style=style_1&description=A+package+to+convert+video+files+to+HLS+with+rotating+key+encryption.&md=1&showWatermark=0&fontSize=150px&images=video-camera "Laravel HLS")](https://packagist.org/packages/achyutn/laravel-hls)

![Packagist Version](https://img.shields.io/packagist/v/achyutn/laravel-hls?label=Latest%20Version)
![Packagist Downloads](https://img.shields.io/packagist/dt/achyutn/laravel-hls?label=Packagist%20Downloads)
![Packagist Stars](https://img.shields.io/packagist/stars/achyutn/laravel-hls?label=Stars)
[![Run Test for Pull Request](https://github.com/achyutkneupane/laravel-hls/actions/workflows/master.yml/badge.svg)](https://github.com/achyutkneupane/laravel-hls/actions/workflows/master.yml)
[![Bump version](https://github.com/achyutkneupane/laravel-hls/actions/workflows/tagrelease.yml/badge.svg)](https://github.com/achyutkneupane/laravel-hls/actions/workflows/tagrelease.yml)

`laravel-hls` is a Laravel package for converting video files into adaptive HLS (HTTP Live Streaming) streams using `ffmpeg`, with built-in AES-128 encryption, queue support, and model-based configuration.

This package makes use of the [laravel-ffmpeg](https://github.com/protonemedia/laravel-ffmpeg) package to handle video
processing and conversion to HLS format. It provides a simple way to convert video files stored in your Laravel application into HLS
streams, which can be used for adaptive bitrate streaming.

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
| `delete_original_file_after_conversion` | A bool to turn on/off deleting the original video after conversion.                            | `bool`   | `false`               |

> 💡 Tip: All disk values must be valid disks defined in your `config/filesystems.php`.

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

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Changelog

See the [CHANGELOG](CHANGELOG.md) for details on changes made in each version.

## Contributing

Contributions are welcome! Please create a pull request or open an issue if you find any bugs or have feature requests.

## Support

If you find this package useful, please consider starring the repository on GitHub to show your support.
