<?php

namespace App\Filament\Resources\CancelledAppResource\Pages;

use App\Filament\Resources\CancelledAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCancelledApps extends ListRecords
{
    protected static string $resource = CancelledAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
