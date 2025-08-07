<?php

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-file.mp4';
});

it("throws exception when column names are incorrect", function () {
    Queue::fake();

    $this->fakeErrorVideoModelObject($this->filename, $this->disk);
    Queue::assertPushed(AchyutN\LaravelHLS\Jobs\QueueHLSConversion::class);

    $video = AchyutN\LaravelHLS\Tests\Models\ErrorVideo::query()->first();
});
