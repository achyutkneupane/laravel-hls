<?php

use AchyutN\LaravelHLS\Tests\Models\Video;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-file.mp4';
});

it('verifies video file is valid', function () {
    /** @var Video $videoModel */
    $this->fakeVideoModelObject($this->filename, $this->disk);

    $this->assertTrue(
        $this->fakeFileExists($this->filename, $this->disk),
        "Fake video file {$this->filename} does not exist on disk {$this->disk}"
    );
    $this->assertTrue(
        FFMpeg::fromDisk($this->disk)->open($this->filename)->getDurationInSeconds() > 0,
        "Video file {$this->filename} on disk {$this->disk} is not valid or has zero duration."
    );
});
