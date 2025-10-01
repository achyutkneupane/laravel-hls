<?php

declare(strict_types=1);

use AchyutN\LaravelHLS\Jobs\QueueHLSConversion;
use AchyutN\LaravelHLS\Tests\Models\Video;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-file.mp4';
    $this->originalFile = 'video-144p.mp4';
});

it('verifies video file is valid', function () {
    /** @var Video $videoModel */
    $this->fakeVideoModelObject($this->filename, $this->disk, $this->originalFile);

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

    $this->fakeVideoModelObject($this->filename, $this->disk, $this->originalFile);

    Queue::assertPushedOn(config('hls.queue_name'), QueueHLSConversion::class);
});

it('does not dispatch job if video_path is empty', function () {
    Queue::fake();

    Video::query()
        ->create([
            config('hls.video_column') => '',
            config('hls.progress_column') => 0,
        ]);

    Queue::assertNotPushed(QueueHLSConversion::class);
});

it('dispatches job when video_path is changed', function () {
    Queue::fake();

    $video = $this->fakeVideoModelObject($this->filename, $this->disk, $this->originalFile);

    $newPath = $this->getFakeVideoFilePath('another.mp4', $this->disk, true);
    $this->fakeVideoFile('another.mp4', $this->disk);

    $video->setVideoPath($newPath);
    $video->save();

    Queue::assertPushed(QueueHLSConversion::class, 2);
});

it('runs job with sync queue driver', function () {
    Config::set('queue.default', 'sync');
    Queue::fake();

    $this->fakeVideoModelObject($this->filename, $this->disk, $this->originalFile);

    Queue::assertPushedOn(config('hls.queue_name'), QueueHLSConversion::class);

    $job = Queue::pushed(QueueHLSConversion::class)->first();
    $job->handle();
});

it('deletes file after conversion', function () {
    Config::set('queue.default', 'sync');
    Config::set('hls.delete_original_file_after_conversion', true);
    Queue::fake();

    $videoModel = $this->fakeVideoModelObject($this->filename, $this->disk, $this->originalFile);

    Queue::assertPushedOn(config('hls.queue_name'), QueueHLSConversion::class);

    $job = Queue::pushed(QueueHLSConversion::class)->first();
    $job->handle();

    $this->assertFalse(
        $this->fakeFileExists($this->filename, $this->disk),
        "Original video file {$this->filename} was not deleted after conversion."
    );

    $this->assertTrue(
        $videoModel->getVideoPath() === null,
        'Video path was not set to null after deleting the original file.'
    );

    $this->assertTrue(
        $videoModel->getHlsPath() !== null,
        'HLS path was not set to null after deleting the original file.'
    );
});
