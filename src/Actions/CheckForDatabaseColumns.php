<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Actions;

use Exception;
use Illuminate\Database\Eloquent\Model;

final class CheckForDatabaseColumns
{
    /**
     * @throws Exception
     */
    public static function handle(Model $model): void
    {
        $videoColumn = $model->getVideoColumn();
        $hlsColumn = $model->getHlsColumn();
        $conversionProgressColumn = $model->getProgressColumn();

        $builder = $model->getConnection()->getSchemaBuilder();

        if (! $builder->hasColumn($model->getTable(), $videoColumn)) {
            throw new Exception("The video column '{$videoColumn}' does not exist in the '{$model->getTable()}' table.");
        }

        if (! $builder->hasColumn($model->getTable(), $hlsColumn)) {
            throw new Exception("The HLS column '{$hlsColumn}' does not exist in the '{$model->getTable()}' table.");
        }

        if (! $builder->hasColumn($model->getTable(), $conversionProgressColumn)) {
            throw new Exception("The conversion progress column '{$conversionProgressColumn}' does not exist in the '{$model->getTable()}' table.");
        }
    }
}
