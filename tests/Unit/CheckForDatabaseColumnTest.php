<?php

use AchyutN\LaravelHLS\Actions\CheckForDatabaseColumns;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'error-video-file.mp4';

    $this->successVideo = $this->fakeVideoModelObject($this->filename, $this->disk);
    $this->errorVideo = $this->fakeErrorVideoModelObject($this->filename, $this->disk);

    $this->connection = $this->errorVideo->getConnection();
});

it('passes all the checks for a valid video model', function () {
    CheckForDatabaseColumns::handle($this->successVideo);
})->throwsNoExceptions();

it('throws exceptions if video_column doesn\'t exists', function () {
    CheckForDatabaseColumns::handle($this->errorVideo);
})
    ->throws(Exception::class, "The video column 'video_path' does not exist in the 'error_videos' table.");

it('throws exceptions if hls_column doesn\'t exists', function () {
    $this->connection->getSchemaBuilder()->table($this->errorVideo->getTable(), function ($table) {
        $table->renameColumn('video_path_error', 'video_path');
    });
    CheckForDatabaseColumns::handle($this->errorVideo);
})
    ->throws(Exception::class, "The HLS column 'hls_path' does not exist in the 'error_videos' table.");

it('throws exceptions if progress_column doesn\'t exists', function () {
    $this->connection->getSchemaBuilder()->table($this->errorVideo->getTable(), function ($table) {
        $table->renameColumn('video_path_error', 'video_path');
        $table->renameColumn('hls_path_error', 'hls_path');
    });
    CheckForDatabaseColumns::handle($this->errorVideo);
})
    ->throws(Exception::class, "The conversion progress column 'conversion_progress' does not exist in the 'error_videos' table.");
