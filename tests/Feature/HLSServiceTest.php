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

it('redirects to temporary url for private S3 files', function () {
    s3filesAssertions('private', 'https://temp-url.test');
});

it('redirects to public url for public S3 files', function () {
    s3filesAssertions('public', 'https://public-url.test');
});

function s3filesAssertions(string $visibility, string $url): void
{
    $disk = Mockery::mock(Illuminate\Contracts\Filesystem\Filesystem::class);
    $adapter = new class {
        public function getTemporaryUrl() {
            // this method is mocked, so no implementation needed
        }
    };

    $disk->shouldReceive('getAdapter')->andReturn($adapter);
    $disk->shouldReceive('getVisibility')->andReturn($visibility);
    if ($visibility === 'private') {
        $disk->shouldReceive('temporaryUrl')->andReturn($url);
    } else {
        $disk->shouldReceive('url')->andReturn($url);
    }

    $service = new \AchyutN\LaravelHLS\Services\HLSService();

    $response = (new ReflectionClass($service))
        ->getMethod('serveFileFromDisk')
        ->invoke($service, $disk, 'path/to/file.ts');

    expect($response->getTargetUrl())->toBe($url);
}
