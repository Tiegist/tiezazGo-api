<?php

namespace App\Observers;

use App\Models\Table;
use App\Services\Qr\QrCodeService;

class TableObserver
{
    public function created(Table $table): void
    {
        app(QrCodeService::class)->generateForTable($table);
    }

    public function updated(Table $table): void
    {
        if ($table->wasChanged(['table_number', 'restaurant_id'])) {
            app(QrCodeService::class)->generateForTable($table);
        }
    }

    public function deleted(Table $table): void
    {
        app(QrCodeService::class)->deleteFilesForTable($table);
    }
}

