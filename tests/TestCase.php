<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Tests;

use AchyutN\LaravelHLS\HLSProvider;
use AchyutN\LaravelHLS\Tests\Models\ErrorVideo;
use AchyutN\LaravelHLS\Tests\Models\Video;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider;

abstract class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();

        FFMpeg::cleanupTemporaryFiles();
    }

    final public function getPackageProviders($app): array
    {
        $providers = [
            ServiceProvider::class,
            HLSProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.connections.the_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('database.default', 'the_test');
        config()->set('app.key', 'base64:Hupx3yAySikrM2/edkZQNQHslgDWYfiBfCuSThJ5SK8=');
        config()->set('laravel-ffmpeg.log_channel', false);
        config()->set('hls.temp_storage_path', sys_get_temp_dir());
        config()->set('hls.temp_hls_storage_path', sys_get_temp_dir());
    }

    protected function setUpDatabase(): void
    {
        app('db')->connection()->getSchemaBuilder()->create('videos', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string(config('hls.video_column'));
            $blueprint->string(config('hls.hls_column'))->nullable();
            $blueprint->integer(config('hls.progress_column'))->default(0);
            $blueprint->timestamps();
        });

        app('db')->connection()->getSchemaBuilder()->create('error_videos', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string(config('hls.video_column')."_error");
            $blueprint->string(config('hls.hls_column')."_error")->nullable();
            $blueprint->integer(config('hls.progress_column')."_error")->default(0);
            $blueprint->timestamps();
        });
    }

    protected function fakeDisk(string $diskName = 'local'): \Illuminate\Contracts\Filesystem\Filesystem
    {
        config()->set("filesystems.disks.{$diskName}", [
            'driver' => 'local',
            'root' => sys_get_temp_dir(),
            'url' => '',
        ]);

        return Storage::disk($diskName);
    }

    protected function fakeVideoFile(string $filename = 'video.mp4', string $disk = 'local', string $file = 'video.mp4'): void
    {
        $filename = $filename ?? Str::uuid().'.mp4';

        $this->fakeDisk($disk)->put($filename, file_get_contents(__DIR__.'/videos/'.$file));
    }

    protected function getFakeVideoFilePath(string $filename = 'video.mp4', string $disk = 'local', bool $fullPath = false): string
    {
        $path = $this->fakeDisk($disk)->url($filename);

        return $fullPath ? $this->fakeDisk($disk)->path($filename) : $path;
    }

    protected function fakeFileExists(string $filename = 'video.mp4', string $disk = 'local'): bool
    {
        return $this->fakeDisk($disk)->exists($filename);
    }

    protected function fakeVideoModelObject(string $filename = 'video.mp4', string $disk = 'local', string $file = 'video.mp4'): Video
    {
        $this->fakeVideoFile($filename, $disk, $file);

        return Video::query()
            ->create([
                config('hls.video_column') => $this->getFakeVideoFilePath($filename, $disk),
                config('hls.progress_column') => 0,
            ]);
    }

    protected function fakeErrorVideoModelObject(string $filename = 'video.mp4', string $disk = 'local', string $file = 'video.mp4'): ErrorVideo
    {
        $this->fakeVideoFile($filename, $disk, $file);

        return ErrorVideo::query()
            ->create([
                config('hls.video_column').'_error' => $this->getFakeVideoFilePath($filename, $disk),
                config('hls.progress_column').'_error' => 0,
            ]);
    }
}
