<?php

namespace App\Filament\Resources\ActiveGayAppResource\Pages;

use App\Actions\SendBroadcastNotification;
use App\Filament\Resources\ActiveGayAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActiveGayApps extends ListRecords
{
    protected static string $resource = ActiveGayAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendBroadcastNotification::make(),
        ];
    }
    
}
