<?php

namespace App\Observers;

use App\Services\SyncChangeRecorder;
use Illuminate\Database\Eloquent\Model;

class SyncChangeObserver
{
    public function __construct(private SyncChangeRecorder $recorder) {}

    public function saved(Model $model): void
    {
        $this->recorder->record($model, 'upsert');
    }

    public function deleted(Model $model): void
    {
        $this->recorder->record($model, 'delete');
    }
}
