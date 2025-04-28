<?php

namespace App\Filament\Resources\GayApplicationResource\Pages;

use App\Filament\Resources\GayApplicationResource;
use App\Models\Status;
use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

class ListGayApplications extends ListRecords
{
    protected static string $resource = GayApplicationResource::class;

    protected function getActions(): array
    {
        return [
            ViewAction::make()
                ->modalHeading('Kapitansiya Fayli')
                ->modalContent(fn ($record) => view('filament.resources.view', [
                    'documentPath' => $record->document_path,
                ]))
        ];
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return GayApplicationResource::getWidgets();
    }
    public function getTabs(): array
    {
        $query = $this->getTableQuery();
        $activePayment = $query->clone()->where('status_id', 2)->count();
        $pendingCount = $query->clone()->where('status_id', 1)->count();
        $completedCount = $query->clone()->where('status_id', 3)->count();
        $cancelledCount = $query->clone()->where('status_id', 4)->count();
        $skippedCount = $query->clone()->where('status_id', 5)->count();
        $allCount = $query->count();
        return [
            'Активен' => Tab::make()
                ->label('Активен')
                ->badge($activePayment)
                ->query(fn ($query) => $query->where('status_id', 2)),
            'Ожидает подтверждение' => Tab::make()
                ->label('Ожидает подтверждение')
                ->badge($pendingCount)
                ->query(fn ($query) => $query->where('status_id', 1)),
            'Успешно завершено' => Tab::make()
                ->label('Успешно завершено')
                ->badge($completedCount)
                ->query(fn ($query) => $query->where('status_id', 3)),
            'Отменён' => Tab::make()
                ->label('Отменён')
                ->badge($cancelledCount)
                ->query(fn ($query) => $query->where('status_id', 4)),
            'Пропущено' => Tab::make()
                ->label('Пропущено')
                ->badge($skippedCount)
                ->query(fn ($query) => $query->where('status_id', 5)),
            null => Tab::make('Все')->badge($allCount),
        ];
    }
}
