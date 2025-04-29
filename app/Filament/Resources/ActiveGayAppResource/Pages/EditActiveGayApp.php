<?php

namespace App\Filament\Resources\ActiveGayAppResource\Pages;

use App\Filament\Resources\ActiveGayAppResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditActiveGayApp extends EditRecord
{
    protected static string $resource = ActiveGayAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
