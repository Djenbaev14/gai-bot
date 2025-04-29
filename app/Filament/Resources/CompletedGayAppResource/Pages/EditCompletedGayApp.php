<?php

namespace App\Filament\Resources\CompletedGayAppResource\Pages;

use App\Filament\Resources\CompletedGayAppResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompletedGayApp extends EditRecord
{
    protected static string $resource = CompletedGayAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
