<?php

namespace App\Filament\Resources\CancelledGayAppResource\Pages;

use App\Filament\Resources\CancelledGayAppResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCancelledGayApp extends EditRecord
{
    protected static string $resource = CancelledGayAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
