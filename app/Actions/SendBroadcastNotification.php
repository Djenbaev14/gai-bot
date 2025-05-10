<?php

namespace App\Actions;

use App\Jobs\SendTelegramMessage;
use App\Models\Branch;
use App\Models\GayApplication;
use App\Models\QueueNumber;
use App\Models\TelegramMessage;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use App\Models\Customer;

class SendBroadcastNotification
{
    public static function make(): Action
    {
        return Action::make('broadcast')
            ->label('Хаммеге телеграм аркалы смс жибериу')
            ->visible(fn () => auth()->user()->id === 1)
            ->form([
                Section::make()
                ->schema([
                    Select::make('branch_id')
                        ->label('Филиалы')
                        ->options(Branch::all()->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->columnSpan(6),
                    Textarea::make('message')
                        ->label('Хабар жибериу')
                        ->required()
                        ->columnSpan(6),
                ])->columns(12)->columnSpan(12)
            ])
            ->action(function (array $data) {
                TelegramMessage::create([
                    'branch_id' => $data['branch_id'],
                    'message' => $data['message'],
                ]);

                $queueNumbers = QueueNumber::with(['customer'])
                    ->where('branch_id', $data['branch_id']) // Agar kerak bo‘lsa
                    // ->whereHas('application', function ($query) {
                    //     $query->where('status_id', 2);
                    // })
                    ->orderBy('queue_number')
                    ->get();

                foreach ($queueNumbers as $index => $item) {
                        $customer = $item->customer;
                
                        if (!$customer || !$customer->telegram_user_id) continue;
                
                        SendTelegramMessage::dispatch(
                            $customer->telegram_user_id,
                            $data['message']
                        )->delay(now()->addSeconds( 3)); // har biri 3 soniya oraliqda
                }
                
                Notification::make()
                ->title('Xabarlar yuborilmoqda ...')
                ->success()
                ->send();

            });
    }
}
