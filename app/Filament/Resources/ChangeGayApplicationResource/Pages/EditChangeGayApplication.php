<?php

namespace App\Filament\Resources\ChangeGayApplicationResource\Pages;

use App\Filament\Resources\ChangeGayApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChangeGayApplication extends EditRecord
{
    protected static string $resource = ChangeGayApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
