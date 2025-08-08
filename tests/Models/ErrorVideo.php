<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Tests\Models;

use AchyutN\LaravelHLS\Traits\ConvertsToHLS;
use Illuminate\Database\Eloquent\Model;

final class ErrorVideo extends Model
{
    use ConvertsToHLS;

    protected $guarded = [];
}
