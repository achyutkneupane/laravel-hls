<?php

use AchyutN\LaravelHLS\Services\HLSService;
use AchyutN\LaravelHLS\Tests\Models\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-test.mp4';

    $this->video = $this->fakeVideoModelObject($this->filename, $this->disk);
    $this->video->hls_path = 'hls/path';
    $this->video->save();

    config()->set('hls.model_aliases', [
        'video' => Video::class,
    ]);

    $this->service = app(HLSService::class);
});

it('returns HLS key content if file exists', function () {
    $keyPath = "{$this->video->getHlsPath()}/{$this->video->getHLSSecretsOutputPath()}/sample.key";
    Storage::disk($this->video->getSecretsDisk())->put($keyPath, 'keydata');

    $response = $this->service->getKey('video', $this->video->id, 'sample.key');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('keydata');
});

it('returns playlist object with resolvers', function () {
    $playlistPath = "{$this->video->getHlsPath()}/{$this->video->getHLSOutputPath()}/playlist.m3u8";
    Storage::disk($this->video->getHlsDisk())->put($playlistPath, "#EXTM3U");
    $playlist = $this->service->getPlaylist('video', $this->video->id);

    expect($playlist)->toBeInstanceOf(\ProtoneMedia\LaravelFFMpeg\Http\DynamicHLSPlaylist::class);
});

it('serves HLS segment file with stream or redirect', function () {
    $segmentPath = "{$this->video->getHlsPath()}/{$this->video->getHLSOutputPath()}/segment.ts";
    Storage::disk($this->video->getHlsDisk())->put($segmentPath, 'segmentdata');

    $response = $this->service->getSegment('video', $this->video->id, 'segment.ts');

    expect($response)
        ->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
});
