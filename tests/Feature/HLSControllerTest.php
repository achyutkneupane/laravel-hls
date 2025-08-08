<?php

use AchyutN\LaravelHLS\Controllers\HLSController;
use AchyutN\LaravelHLS\Jobs\QueueHLSConversion;
use AchyutN\LaravelHLS\Services\HLSService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->mockService = Mockery::mock(HLSService::class);
    $this->app->instance(HLSService::class, $this->mockService);

    Route::get('/hls/key/{model}/{id}/{key}', [HLSController::class, 'key'])->name('hls.key');
    Route::get('/hls/segment/{model}/{id}/{filename}', [HLSController::class, 'segment'])->name('hls.segment');
    Route::get('/hls/playlist/{model}/{id}/{playlist?}', [HLSController::class, 'playlist'])->name('hls.playlist');
});

it('returns key when signature is valid', function () {
    $signedUrl = URL::signedRoute('hls.key', [
        'model' => 'video',
        'id' => 1,
        'key' => 'enc.key'
    ]);

    $expectedResponse = new Response('key-data', 200);
    $this->mockService
        ->shouldReceive('getKey')
        ->once()
        ->with('video', 1, 'enc.key')
        ->andReturn($expectedResponse);

    $this->get($signedUrl)
        ->assertOk()
        ->assertSee('key-data');
});

it('returns 401 for key when signature is invalid', function () {
    $url = route('hls.key', [
        'model' => 'video',
        'id' => 1,
        'key' => 'enc.key'
    ]);

    $this->get($url)->assertStatus(401);
});

it('returns 404 when playlist file does not exist', function () {
    $this->mockService
        ->shouldReceive('getPlaylist')
        ->once()
        ->with('video', 1, 'playlist.m3u8')
        ->andThrow(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException());

    $this->get(route('hls.playlist', [
        'model' => 'video',
        'id' => 1,
    ]))->assertNotFound();
});

it('returns segment when signature is valid', function () {
    $signedUrl = URL::signedRoute('hls.segment', [
        'model' => 'video',
        'id' => 1,
        'filename' => 'file.ts'
    ]);

    $expectedResponse = new StreamedResponse();
    $this->mockService
        ->shouldReceive('getSegment')
        ->once()
        ->with('video', 1, 'file.ts')
        ->andReturn($expectedResponse);

    $this->get($signedUrl)->assertOk();
});
