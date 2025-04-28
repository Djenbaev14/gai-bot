<?php

namespace App\Filament\Widgets;
use App\Models\GayApplication;
use App\Models\Status;
use Carbon\Carbon;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApplicationStats extends BaseWidget
{protected function getStats(): array
    {
        // Get status IDs for 'completed' and 'not_arrived'
        $completedStatusId = Status::where('key', 'completed')->first()->id;
        $notArrivedStatusId = Status::where('key', 'not_arrived')->first()->id;

        // Get today's date range
        $today = Carbon::today();

        // Count completed applications for today
        $completedCount = GayApplication::where('status_id', $completedStatusId)
            ->whereDate('created_at', $today)
            ->count();

        // Count not_arrived applications for today
        $notArrivedCount = GayApplication::where('status_id', $notArrivedStatusId)
            ->whereDate('created_at', $today)
            ->count();

        return [
            Stat::make('Completed Today', $completedCount)
                ->description('Number of applications completed today')
                ->color('success'),
            Stat::make('Not Arrived Today', $notArrivedCount)
                ->description('Number of applications not arrived today')
                ->color('danger'),
        ];
    }
}
