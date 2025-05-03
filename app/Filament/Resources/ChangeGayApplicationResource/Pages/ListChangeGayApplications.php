<?php

namespace App\Filament\Resources\ChangeGayApplicationResource\Pages;

use App\Filament\Resources\ChangeGayApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChangeGayApplications extends ListRecords
{
    protected static string $resource = ChangeGayApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
