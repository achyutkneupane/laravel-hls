<?php

use AchyutN\LaravelHLS\Tests\Models\Video;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-file.mp4';

    /** @var Video $video */
    $this->video = $this->fakeVideoModelObject($this->filename, $this->disk);
});

it("getVideoPath returns the correct path", function () {
    $videoPath = $this->video->getVideoPath();

    $this->assertEquals(
        $this->getFakeVideoFilePath($this->filename, $this->disk),
        $videoPath,
        "getVideoPath did not return the expected path."
    );
});

it("setVideoPath updates the video path", function () {
    $newPath = 'new-video-file.mp4';
    $this->video->setVideoPath($newPath);

    $this->assertEquals(
        $newPath,
        $this->video->getVideoPath(),
        "setVideoPath did not update the video path correctly."
    );
});

it("setHlsPath updates the HLS path", function () {
    $newHlsPath = 'new-hls-file.m3u8';
    $this->video->setHlsPath($newHlsPath);

    $this->assertEquals(
        $newHlsPath,
        $this->video->getHlsPath(),
        "setHlsPath did not update the HLS path correctly."
    );
});

it("getProgress returns the correct progress", function () {
    $this->assertEquals(
        0,
        $this->video->getProgress(),
        "getProgress did not return the expected progress value."
    );
});

it("setProgress updates the progress", function () {
    $this->video->setProgress(75);

    $this->assertEquals(
        75,
        $this->video->getProgress(),
        "setProgress did not update the progress correctly."
    );
});


it('returns the default temp storage output path from config', function () {
    unset($this->video->tempStorageOutputPath);

    config()->set('hls.temp_storage_path', 'temp_default_path');

    $this->assertEquals(
        'temp_default_path',
        $this->video->getTempStorageOutputPath(),
        'Expected getTempStorageOutputPath() to return config fallback value.'
    );
});

it('getVideoDisk returns default video disk from config', function () {
    config()->set('hls.video_disk', 'test_video_disk');

    $this->assertEquals(
        'test_video_disk',
        $this->video->getVideoDisk(),
        'Expected getVideoDisk() to return config-defined value.'
    );
});

it('getHlsDisk returns default HLS disk from config', function () {
    config()->set('hls.hls_disk', 'test_hls_disk');

    $this->assertEquals(
        'test_hls_disk',
        $this->video->getHlsDisk(),
        'Expected getHlsDisk() to return config-defined value.'
    );
});

it('getSecretsDisk returns default secrets disk from config', function () {
    config()->set('hls.secrets_disk', 'test_secrets_disk');

    $this->assertEquals(
        'test_secrets_disk',
        $this->video->getSecretsDisk(),
        'Expected getSecretsDisk() to return config-defined value.'
    );
});

it('getHLSOutputPath returns default HLS output path from config', function () {
    config()->set('hls.hls_output_path', 'hls_output_dir');

    $this->assertEquals(
        'hls_output_dir',
        $this->video->getHLSOutputPath(),
        'Expected getHLSOutputPath() to return config-defined value.'
    );
});

it('getHLSSecretsOutputPath returns default HLS secrets output path from config', function () {
    config()->set('hls.secrets_output_path', 'hls_secrets_dir');

    $this->assertEquals(
        'hls_secrets_dir',
        $this->video->getHLSSecretsOutputPath(),
        'Expected getHLSSecretsOutputPath() to return config-defined value.'
    );
});

