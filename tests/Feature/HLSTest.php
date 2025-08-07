<?php

declare(strict_types=1);

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

it('can push job when video is saved', function () {
    Queue::fake();

    /** @var Video $videoModel */
    $videoModel = $this->fakeVideoModelObject($this->filename, $this->disk);
    $videoModel->save();

    Queue::assertPushedOn(config('hls.queue_name'), AchyutN\LaravelHLS\Jobs\QueueHLSConversion::class);
});
