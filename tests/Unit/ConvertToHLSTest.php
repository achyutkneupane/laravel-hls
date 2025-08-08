<?php

use AchyutN\LaravelHLS\Actions\ConvertToHLS;

beforeEach(function () {
    $this->disk = 'public';

    $this->playlist = "playlist.m3u8";

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
    it("throws exception for invalid video", function () {
        ConvertToHLS::convertToHLS($this->invalidVideo->getVideoPath(), 'invalid-folder', $this->invalidVideo);
    })->throws(Exception::class, 'Failed to open or probe video file.');

    it("throws exception for invalid format", function () {
        $original_path = $this->video144p->getVideoPath();
        $folderName = uuid_create();

        Config::set('hls.resolutions', [
            '360p' => '640-360',
        ]);

        ConvertToHLS::convertToHLS($original_path, $folderName, $this->video144p);
    })->throws(Exception::class, 'Invalid resolution format: 640-360. Expected format is \'{width}x{height}\'.');
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
