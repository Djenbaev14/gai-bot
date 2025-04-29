<?php

namespace App\Filament\Resources\CompletedGayAppResource\Pages;

use App\Filament\Resources\CompletedGayAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompletedGayApps extends ListRecords
{
    protected static string $resource = CompletedGayAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
