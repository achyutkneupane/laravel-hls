<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Tests;

use AchyutN\LaravelHLS\HLSProvider;
use AchyutN\LaravelHLS\Tests\Models\Video;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        config()->set('hls.video_column', 'video_path');
    }

    protected function fakeDisk($diskName = 'local'): \Illuminate\Contracts\Filesystem\Filesystem
    {
        config()->set("filesystems.disks.{$diskName}", [
            'driver' => 'memory',
            'root' => sys_get_temp_dir(),
        ]);

        Storage::fake($diskName);

        return Storage::disk($diskName);
    }

    protected function fakeVideoFile(string $filename = 'video.mp4', string $disk = 'local'): void
    {
        $this->fakeDisk($disk)->put($filename, file_get_contents(__DIR__.'/videos/video.mp4'));
    }

    protected function getFakeVideoFilePath(string $filename = 'video.mp4', string $disk = 'local'): string
    {
        return $this->fakeDisk($disk)->path($filename);
    }

    protected function fakeVideoModelObject(string $filename = 'video.mp4', string $disk = 'local'): Video
    {
        $this->fakeVideoFile($filename, $disk);

        return new Video([
            config('hls.video_column') => $this->getFakeVideoFilePath($filename, $disk),
            config('hls.hls_column') => 'hls',
            config('hls.progress_column') => 0,
        ]);
    }
}
