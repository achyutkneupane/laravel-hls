<?php

declare(strict_types=1);

use AchyutN\LaravelHLS\Actions\ConvertToHLS;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

beforeEach(function () {
    $this->disk = 'public';

    $this->playlist = 'playlist.m3u8';

    $this->video720p = $this->fakeVideoModelObject('720p-video.mp4', $this->disk);
    $this->video144p = $this->fakeVideoModelObject('144p-video.mp4', $this->disk, 'video-144p.mp4');
    $this->invalidVideo = $this->fakeVideoModelObject('non-existent-video.mp4', $this->disk, 'error-video.mp4');
});

describe('generates HLS playlist, segments and keys for a valid video model', function () {
    test('720p', function () {
        $original_path = $this->video720p->getVideoPath();
        $folderName = uuid_create();

        ConvertToHLS::convertToHLS($original_path, $folderName, $this->video720p);

        $hlsDiskName = $this->video720p->getHlsDisk();
        $hlsDisk = $this->fakeDisk($hlsDiskName);

        $secretsDiskName = $this->video720p->getSecretsDisk();
        $secretsDisk = $this->fakeDisk($secretsDiskName);

        $hlsOutputPath = $this->video720p->getHLSOutputPath();
        $secretsOutputPath = $this->video720p->getHLSSecretsOutputPath();

        $playlistAndSegmentDirectory = "{$folderName}/{$hlsOutputPath}";
        $keyDirectory = "{$folderName}/{$secretsOutputPath}";

        $playlistPath = "{$playlistAndSegmentDirectory}/{$this->playlist}";

        performAssertions($hlsDisk, $playlistPath, $playlistAndSegmentDirectory, $secretsDisk, $keyDirectory);
    });

    test('144p', function () {
        $original_path = $this->video144p->getVideoPath();
        $folderName = uuid_create();

        ConvertToHLS::convertToHLS($original_path, $folderName, $this->video144p);

        $hlsDiskName = $this->video144p->getHlsDisk();
        $hlsDisk = $this->fakeDisk($hlsDiskName);

        $secretsDiskName = $this->video144p->getSecretsDisk();
        $secretsDisk = $this->fakeDisk($secretsDiskName);

        $hlsOutputPath = $this->video144p->getHLSOutputPath();
        $secretsOutputPath = $this->video144p->getHLSSecretsOutputPath();

        $playlistAndSegmentDirectory = "{$folderName}/{$hlsOutputPath}";
        $keyDirectory = "{$folderName}/{$secretsOutputPath}";

        $playlistPath = "{$playlistAndSegmentDirectory}/{$this->playlist}";

        performAssertions($hlsDisk, $playlistPath, $playlistAndSegmentDirectory, $secretsDisk, $keyDirectory);
    });
});

describe('exceptions', function () {
    it('throws exception for invalid video', function () {
        ConvertToHLS::convertToHLS($this->invalidVideo->getVideoPath(), 'invalid-folder', $this->invalidVideo);
    })->throws(Exception::class, 'Failed to open or probe video file.');

    it('throws exception for invalid format', function () {
        $original_path = $this->video144p->getVideoPath();
        $folderName = uuid_create();

        Config::set('hls.resolutions', [
            '360p' => '640-360',
        ]);

        ConvertToHLS::convertToHLS($original_path, $folderName, $this->video144p);
    })->throws(Exception::class, 'Invalid resolution format: 640-360. Expected format is \'{width}x{height}\'.');

    it('throws runtime exception when save fails during conversion', function () {
        // Mock media object to return fake resolution and bitrate
        $mediaMock = Mockery::mock();
        $mediaMock->shouldReceive('getFormat->get')->andReturn(1000); // bitrate in bits
        $streamMock = Mockery::mock();
        $streamMock->shouldReceive('getDimensions->getWidth')->andReturn(1280);
        $streamMock->shouldReceive('getDimensions->getHeight')->andReturn(720);
        $mediaMock->shouldReceive('getVideoStream')->andReturn($streamMock);

        // Mock export chain so save() throws
        $exportMock = Mockery::mock();
        $exportMock->shouldReceive('toDisk')->andReturnSelf();
        $exportMock->shouldReceive('addFormat')->andReturnSelf();
        $exportMock->shouldReceive('onProgress')->andReturnSelf();
        $exportMock->shouldReceive('save')
            ->andThrow(new Exception('Simulated failure during save'));

        // Mock FFMpeg::fromDisk()->open() calls
        FFMpeg::shouldReceive('fromDisk')
            ->with('video-disk')
            ->andReturnSelf()
            ->twice(); // one for probe, one for export

        FFMpeg::shouldReceive('open')
            ->andReturn($mediaMock, $exportMock); // first returns mediaMock, second returns exportMock

        FFMpeg::shouldReceive('exportForHLS')->andReturn($exportMock);
        FFMpeg::shouldReceive('cleanupTemporaryFiles')->andReturnNull();

        // Fake model with required methods
        $model = Mockery::mock(Illuminate\Database\Eloquent\Model::class);
        $model->shouldReceive('getVideoDisk')->andReturn('video-disk');
        $model->shouldReceive('getHlsDisk')->andReturn('hls-disk');
        $model->shouldReceive('getSecretsDisk')->andReturn('secrets-disk');
        $model->shouldReceive('getHLSOutputPath')->andReturn('hls');
        $model->shouldReceive('getHLSSecretsOutputPath')->andReturn('secrets');
        $model->shouldReceive('getHLSResolutions')->andReturn(['720p' => '1280x720']);
        $model->shouldReceive('getHLSRootFolderPath')->andReturn('output-folder');

        config()->set('hls.bitrates', ['720p' => 1500]);
        config()->set('hls.enable_encryption', false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to prepare formats for HLS conversion');

        ConvertToHLS::convertToHLS('input.mp4', 'output-folder', $model);
    });

    it('throws InvalidArgumentException on invalid resolution format in renameResolution', function () {
        $method = new ReflectionMethod(ConvertToHLS::class, 'renameResolution');

        $invalidResolution = 'invalid_format';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid resolution format: {$invalidResolution}. Expected format is '{width}x{height}'.");

        $method->invoke(null, $invalidResolution);
    });
});

it('confirms temporary directories', function () {
    $original_path = $this->video144p->getVideoPath();
    $folderName = uuid_create();

    ConvertToHLS::convertToHLS($original_path, $folderName, $this->video144p);

    expect(config('laravel-ffmpeg.temporary_files_encrypted_hls'))
        ->toBe(config('hls.temp_hls_storage_path'));

    expect(config('laravel-ffmpeg.temporary_files_root'))
        ->toBe(config('hls.temp_storage_path'));
});

function performAssertions($hlsDisk, $playlistPath, $playlistAndSegmentDirectory, $secretsDisk, $keyDirectory): void
{
    expect($hlsDisk->exists($playlistPath))->toBeTrue();
    expect($hlsDisk->exists($playlistAndSegmentDirectory))->toBeTrue();
    expect($hlsDisk->files($playlistAndSegmentDirectory))->not->toBeEmpty();

    $files = $hlsDisk->files($playlistAndSegmentDirectory);
    foreach ($files as $file) {
        expect(pathinfo($file, PATHINFO_EXTENSION))->toBeIn(['m3u8', 'ts']);
    }

    expect($secretsDisk->exists($keyDirectory))->toBeTrue();
    expect($secretsDisk->files($keyDirectory))->not->toBeEmpty();
    $keyFiles = $secretsDisk->files($keyDirectory);
    foreach ($keyFiles as $file) {
        expect(pathinfo($file, PATHINFO_EXTENSION))->toBe('key');
    }

    $playlistContent = $hlsDisk->get($playlistPath);
    expect($playlistContent)->toContain('#EXTM3U');
    expect($playlistContent)->toContain('#EXT-X-STREAM-INF');
}
