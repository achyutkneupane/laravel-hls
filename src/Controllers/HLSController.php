<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Controllers;

use AchyutN\LaravelHLS\Services\HLSService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class HLSController
{
    public function __construct(private HLSService $service) {}

    public function key(string $model, int|string $id, string $key): \Illuminate\Http\Response
    {
        abort_unless(request()->hasValidSignature(), 401);

        return $this->service->getKey($model, $id, $key);
    }

    public function segment(string $model, int|string $id, string $filename): RedirectResponse|StreamedResponse
    {
        abort_unless(request()->hasValidSignature(), 401);

        return $this->service->getSegment($model, $id, $filename);
    }

    public function playlist(string $model, int|string $id, string $playlist = 'playlist.m3u8'): \ProtoneMedia\LaravelFFMpeg\Http\DynamicHLSPlaylist
    {
        return $this->service->getPlaylist($model, $id, $playlist);
    }
}
