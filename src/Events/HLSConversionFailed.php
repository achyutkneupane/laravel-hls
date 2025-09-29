<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when HLS conversion fails.
 */
final readonly class HLSConversionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}
}
