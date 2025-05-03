<?php

namespace App\Filament\Resources\NoShowsGayApplicationResource\Pages;

use App\Filament\Resources\NoShowsGayApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNoShowsGayApplication extends EditRecord
{
    protected static string $resource = NoShowsGayApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
