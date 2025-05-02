<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\GayApplication;
use App\Models\QueueNumber;
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
                    TextInput::make('limit')
                        ->label('Неше адамга жибериу крк')
                        ->required()
                        ->columnSpan(6),
                    Textarea::make('message')
                        ->label('Хабар жибериу')
                        ->required()
                        ->columnSpan(6),
                ])->columns(12)->columnSpan(12)
            ])
            ->action(function (array $data) {
                $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                // $applications = GayApplication::with('customer')
                // ->where('branch_id', $data['branch_id'])
                // ->where('status_id', 2)
                // ->limit($data['limit']) // xavfsizlik uchun limit
                // ->get();
                
                $queueNumbers = QueueNumber::with(['application.customer'])
                    ->where('branch_id', $data['branch_id']) // Agar kerak bo‘lsa
                    ->orderBy('queue_number')
                    ->limit($data['limit'])
                    ->get();
                    Log::info($queueNumbers);

                foreach ($queueNumbers as $item) {
                        $customer = $item->application?->customer;
                
                        if (!$customer || !$customer->telegram_user_id) continue;
                
                        try {
                            $telegram->sendMessage([
                                'chat_id' => $customer->telegram_user_id,
                                'text' => $data['message'],
                            ]);
                            usleep(500000); // 0.4 soniya kutish
                        }  catch (\Throwable $th) {
                                    $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                                    $telegram->sendMessage([
                                        'chat_id' => env('TELEGRAM_MY_CHAT_ID'),
                                        'text' => $th->getMessage() . ' on line ' . $th->getLine() . ' in ' . $th->getFile()
                                    ]);
                        }
                }
                // foreach ($applications as $application) {
                //     $customer = $application->customer;
            
                //     if (!$customer || !$customer->telegram_user_id) {
                //         continue;
                //     }
            
                //     try {
                //         $telegram->sendMessage([
                //             'chat_id' => $customer->telegram_user_id,
                //             'text' => $data['message'],
                //         ]);
            
                //         usleep(500000); // 0.5 soniya delay — spamdan saqlanish uchun
            
                //     } catch (\Throwable $th) {
                //         $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                //         $telegram->sendMessage([
                //             'chat_id' => env('TELEGRAM_MY_CHAT_ID'),
                //             'text' => $th->getMessage() . ' on line ' . $th->getLine() . ' in ' . $th->getFile()
                //         ]);
                //     }
                // }
            
                Notification::make()
                    ->title('Xabar muvaffaqiyatli yuborildi!')
                    ->success()
                    ->send();

            });
    }
}
