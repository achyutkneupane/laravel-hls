<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-file.mp4';

    $this->playlist = "playlist.m3u8";

    $this->successVideo = $this->fakeVideoModelObject($this->filename, $this->disk);
});

it('generates HLS playlist, segments and keys for a valid video model', function () {
    $original_path = $this->successVideo->getVideoPath();
    $folderName = uuid_create();

    \AchyutN\LaravelHLS\Actions\ConvertToHLS::convertToHLS($original_path, $folderName, $this->successVideo);

    $hlsDiskName = $this->successVideo->getHlsDisk();
    $hlsDisk = $this->fakeDisk($hlsDiskName);

    $secretsDiskName = $this->successVideo->getSecretsDisk();
    $secretsDisk = $this->fakeDisk($secretsDiskName);

    $hlsOutputPath = $this->successVideo->getHLSOutputPath();
    $secretsOutputPath = $this->successVideo->getHLSSecretsOutputPath();

    $playlistAndSegmentDirectory = "{$folderName}/{$hlsOutputPath}";
    $keyDirectory = "{$folderName}/{$secretsOutputPath}";

    $playlistPath = "{$playlistAndSegmentDirectory}/{$this->playlist}";

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
});
