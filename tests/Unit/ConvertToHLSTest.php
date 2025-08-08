<?php

use AchyutN\LaravelHLS\Actions\ConvertToHLS;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-file.mp4';

    $this->playlist = "playlist.m3u8";

    $this->video360p = $this->fakeVideoModelObject($this->filename, $this->disk);
    $this->video144p = $this->fakeVideoModelObject($this->filename, $this->disk, 'video-144p.mp4');
});

describe('generates HLS playlist, segments and keys for a valid video model', function () {
    test('720p', function () {
        $original_path = $this->video360p->getVideoPath();
        $folderName = uuid_create();

        ConvertToHLS::convertToHLS($original_path, $folderName, $this->video360p);

        $hlsDiskName = $this->video360p->getHlsDisk();
        $hlsDisk = $this->fakeDisk($hlsDiskName);

        $secretsDiskName = $this->video360p->getSecretsDisk();
        $secretsDisk = $this->fakeDisk($secretsDiskName);

        $hlsOutputPath = $this->video360p->getHLSOutputPath();
        $secretsOutputPath = $this->video360p->getHLSSecretsOutputPath();

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

    test('144p', function () {
        $original_path = $this->video360p->getVideoPath();
        $folderName = uuid_create();

        ConvertToHLS::convertToHLS($original_path, $folderName, $this->video360p);

        $hlsDiskName = $this->video360p->getHlsDisk();
        $hlsDisk = $this->fakeDisk($hlsDiskName);

        $secretsDiskName = $this->video360p->getSecretsDisk();
        $secretsDisk = $this->fakeDisk($secretsDiskName);

        $hlsOutputPath = $this->video360p->getHLSOutputPath();
        $secretsOutputPath = $this->video360p->getHLSSecretsOutputPath();

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
});
