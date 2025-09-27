<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class HLSService
{
    public function getKey(string $model, int|string $id, string $key): Response
    {
        $resolvedModel = $this->resolveModel($model)->query()->findOrFail($id);

        $path = "{$resolvedModel->getHlsPath()}/{$resolvedModel->getHLSSecretsOutputPath()}/{$key}";
        if (! Storage::disk($resolvedModel->getSecretsDisk())->exists($path)) {
            abort(404);
        }

        return response(Storage::disk($resolvedModel->getSecretsDisk())->get($path), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$key.'"',
        ]);
    }

    public function getSegment(string $model, int|string $id, string $filename): RedirectResponse|StreamedResponse
    {
        $resolvedModel = $this->resolveModel($model)->query()->findOrFail($id);

        $path = "{$resolvedModel->getHlsPath()}/{$resolvedModel->getHLSOutputPath()}/{$filename}";
        if (! Storage::disk($resolvedModel->getHlsDisk())->exists($path)) {
            abort(404);
        }

        // Use the most efficient method based on the storage driver.
        return $this->serveFileFromDisk(Storage::disk($resolvedModel->getHlsDisk()), $path);
    }

    public function getPlaylist(string $model, int|string $id, string $playlist = 'playlist.m3u8'): \ProtoneMedia\LaravelFFMpeg\Http\DynamicHLSPlaylist
    {
        $resolvedModel = $this->resolveModel($model)->query()->findOrFail($id);

        $path = "{$resolvedModel->getHlsPath()}/{$resolvedModel->getHLSOutputPath()}/{$playlist}";
        if (! Storage::disk($resolvedModel->getHlsDisk())->exists($path)) {
            abort(404);
        }

        return FFMpeg::dynamicHLSPlaylist($resolvedModel->getHlsDisk())
            ->fromDisk($resolvedModel->getHlsDisk())
            ->open($path)
            ->setKeyUrlResolver(fn ($key) => URL::signedRoute(
                'hls.key',
                ['model' => $model, 'id' => $id, 'key' => $key]
            ))
            ->setMediaUrlResolver(fn ($filename) => URL::signedRoute(
                'hls.segment',
                ['model' => $model, 'id' => $id, 'filename' => $filename]
            ))
            ->setPlaylistUrlResolver(fn ($filename) => URL::signedRoute(
                'hls.playlist',
                ['model' => $model, 'id' => $id, 'playlist' => $filename]
            ));
    }

    private function resolveModel(string $type): Model
    {
        try {
            return app(config('hls.model_aliases')[$type]);
        } catch (Throwable $e) {
            Log::error("Failed to resolve model for type [{$type}]: ".$e->getMessage());
            abort(404, "Unknown model type [{$type}]");
        }
    }

    /**
     * Intelligently serves a file from a disk, using the most efficient method.
     *
     * - For S3: Redirects to a temporary signed URL (for private files) or a public URL.
     * - For Local: Streams the file directly.
     */
    private function serveFileFromDisk(Filesystem $disk, string $path): StreamedResponse|RedirectResponse
    {
        $adapter = $disk->getAdapter();

        // Check if the driver is S3 or another cloud service that supports temporary URLs.
        if (method_exists($adapter, 'getTemporaryUrl')) {
            // For private files, generate a temporary signed URL to offload the download to S3.
            // This is the most performant and scalable option.
            // The visibility check is a good practice, though temporaryUrl works for public files too.
            if ($disk->getVisibility($path) === 'private') {
                return redirect($disk->temporaryUrl($path, now()->addMinutes(10)));
            }

            // For public files, just redirect to the permanent public URL.
            return redirect($disk->url($path));
        }

        // For local storage or other drivers, fall back to a standard streamed response.
        return $disk->response($path);
    }
}
