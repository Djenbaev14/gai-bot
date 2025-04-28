<?php

namespace App\Filament\Resources\GayApplicationResource\Pages;

use App\Filament\Resources\GayApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGayApplication extends EditRecord
{
    protected static string $resource = GayApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
