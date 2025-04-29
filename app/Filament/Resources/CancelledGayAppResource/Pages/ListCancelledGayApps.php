<?php

namespace App\Filament\Resources\CancelledGayAppResource\Pages;

use App\Filament\Resources\CancelledGayAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCancelledGayApps extends ListRecords
{
    protected static string $resource = CancelledGayAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
