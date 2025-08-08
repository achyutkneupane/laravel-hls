<?php

declare(strict_types=1);

use AchyutN\LaravelHLS\Services\HLSService;
use AchyutN\LaravelHLS\Tests\Models\Video;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->disk = 'public';
    $this->filename = 'video-test.mp4';

    $this->video = $this->fakeVideoModelObject($this->filename, $this->disk);
    $this->video->hls_path = 'hls/path';
    $this->video->save();

    Config::set('hls.model_aliases', [
        'video' => Video::class,
    ]);

    $this->service = app(HLSService::class);
});

it('returns HLS key content if file exists', function () {
    $keyPath = "{$this->video->getHlsPath()}/{$this->video->getHLSSecretsOutputPath()}/sample.key";
    $this->fakeDisk($this->video->getSecretsDisk())->put($keyPath, 'keydata');

    $response = $this->service->getKey('video', $this->video->id, 'sample.key');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('keydata');
});

it('throws 404 if HLS key file does not exist', function () {
    $this->service->getKey('video', $this->video->id, 'nonexistent.key');
})->throws(NotFoundHttpException::class);

it('returns playlist object with resolvers', function () {
    $playlistPath = "{$this->video->getHlsPath()}/{$this->video->getHLSOutputPath()}/playlist.m3u8";
    $this->fakeDisk($this->video->getHlsDisk())->put($playlistPath, '#EXTM3U');
    $playlist = $this->service->getPlaylist('video', $this->video->id);

    expect($playlist)->toBeInstanceOf(ProtoneMedia\LaravelFFMpeg\Http\DynamicHLSPlaylist::class);
});

it('throws 404 if HLS playlist file does not exist', function () {
    $this->service->getPlaylist('video', $this->video->id, 'nonexistent.m3u8');
})->throws(NotFoundHttpException::class);

it('serves HLS segment file with stream or redirect', function () {
    $segmentPath = "{$this->video->getHlsPath()}/{$this->video->getHLSOutputPath()}/segment.ts";
    $this->fakeDisk($this->video->getHlsDisk())->put($segmentPath, 'segmentdata');

    $response = $this->service->getSegment('video', $this->video->id, 'segment.ts');

    expect($response)
        ->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
});

it('throws 404 if HLS segment file does not exist', function () {
    $this->service->getSegment('video', $this->video->id, 'nonexistent.ts');
})->throws(NotFoundHttpException::class);

it('throws exception if modal alias is not defined', function () {
    Config::set('hls.model_aliases', []);

    $keyPath = "{$this->video->getHlsPath()}/{$this->video->getHLSSecretsOutputPath()}/sample.key";
    $this->fakeDisk($this->video->getSecretsDisk())->put($keyPath, 'keydata');

    $this->service->getKey('video', $this->video->id, 'sample.key');
})->throws(NotFoundHttpException::class, 'Unknown model type [video]');
