<?php

namespace App\Filament\Widgets;
use App\Models\GayApplication;
use App\Models\Status;
use Carbon\Carbon;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget;

class ApplicationStats extends StatsOverviewWidget
{
    use InteractsWithForms;

    public ?string $period = 'today'; // Default holat: bugun

    protected function getFormSchema(): array
    {
        return [
            Select::make('period')
                ->label('Vaqt oralig‘ini tanlang')
                ->options([
                    'today' => 'Bugun',
                    'week' => '1 hafta',
                    'month' => '1 oy',
                ])
                ->default('today')
                ->reactive() // tanlaganda formni avtomatik yangilash uchun
                ->afterStateUpdated(fn () => $this->updateStats()),
        ];
    }

    protected function getStats(): array
    {
        $completedStatusId = Status::where('key', 'completed')->first()?->id;
        $notArrivedStatusId = Status::where('key', 'not_arrived')->first()?->id;

        $query = GayApplication::query();

        if ($this->period === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($this->period === 'week') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($this->period === 'month') {
            $query->where('created_at', '>=', Carbon::now()->subMonth());
        }

        $completedCount = (clone $query)->where('status_id', $completedStatusId)->count();
        $notArrivedCount = (clone $query)->where('status_id', $notArrivedStatusId)->count();

        return [
            Stat::make('Completed', $completedCount)
                ->description('Tamamlangan arizalar')
                ->color('success'),
            Stat::make('Not Arrived', $notArrivedCount)
                ->description('Kelmagan arizalar')
                ->color('danger'),
        ];
    }
}
