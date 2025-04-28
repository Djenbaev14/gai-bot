<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GayApplicationResource\Pages;
use App\Filament\Resources\GayApplicationResource\RelationManagers;
use App\Models\Customer;
use App\Models\GayApplication;
use App\Models\QueueNumber;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;

class GayApplicationResource extends Resource
{
    protected static ?string $model = GayApplication::class;

    protected static ?string $navigationIcon = 'fas-tasks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()
                ->schema([
                    TextInput::make('customer_id'),
                    TextInput::make('full_name')
                        ->label('ФИО')
                        ->maxLength(255),
                    TextInput::make('passport')
                        ->label('Паспотр')
                        ->maxLength(255),
            
                    FileUpload::make('document_path')
                        ->label('Лого')
                        ->image()
                        ->disk('public') 
                        ->directory('uploads/images')
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            '16:9',
                            '4:3',
                            '1:1',
                        ])
                        ->columnSpan(12),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('queueNumber.queue_number')
                    ->label('Номер')
                    ->searchable(),
                TextColumn::make('customer.full_name')
                    ->label('ФИО')
                    ->searchable(),
                TextColumn::make('customer.passport')
                    ->label('Паспорт')
                    ->searchable(),
                TextColumn::make('status.name')
                    ->label('Статус'),
                    
                TextColumn::make('status.name')
                ->label('Статус')
                ->html()
                ->formatStateUsing(function ($state, $record) {
                    $color = $record->status->color ?? '#999';
                    return "<span style='
                        background-color: {$color};
                        color: white;
                        padding: 4px 8px;
                        border-radius: 8px;
                        font-size: 12px;
                        display: inline-block;
                    '>" . ucfirst($state) . "</span>";
                }),
            ])
            ->defaultPaginationPageOption(5)
            ->actions([
                
                // Action::make('active')
                //     ->label('Актив')
                //     ->color('success')
                //     ->icon('fas-circle-check')
                //     ->action(function (GayApplication $record) {
                        
                //             // Navbat raqamini olish: eng kichik bo'lmagan raqamni olish
                //             $lastQueueNumber = QueueNumber::max('queue_number'); // Oxirgi navbat raqamini olamiz
                //             $nextQueueNumber = $lastQueueNumber + 1; // Keyingi raqamni olish
                    
                //             // Yangi navbat raqamini yaratish
                //             QueueNumber::create([
                //                 'customer_id' => $record->customer_id,
                //                 'gay_application_id' => $record->id,
                //                 'queue_number' => $nextQueueNumber,
                //             ]);
                    
                //             $customer = Customer::find($record->customer_id);
                //             $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                //             $telegram->sendMessage([
                //                 'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                //                 'text' => "✅ Сизиң дизимнен өтиў сораўыңыз тастыйықланды!\n\nНәўбет номериңиз: $nextQueueNumber",
                //             ]);
                //         $record->update(['status_id' => 2]);
                //             Notification::make()
                //             ->title('Buyurtma holati yangilandi')
                //             ->success()
                //             ->send();
                //     })
                //     ->visible(fn (GayApplication $record): bool => $record->status_id == 1),

                //     Action::make('cancelled')
                //     ->label('Деактив')
                //     ->color('danger')
                //     ->icon('fas-circle-check')
                //     ->action(function (GayApplication $record) {
                        
                //         $customer = Customer::find($record->customer_id);
                //         $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                //         $telegram->sendMessage([
                //             'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                //             'text' => "❌ Сизиң дизимнен өтиў сораўыңыз бийкар етилди!",
                //         ]);
                //         $record->update(['status_id' => 4]);
                //             Notification::make()
                //             ->title('Buyurtma holati yangilandi')
                //             ->success()
                //             ->send();
                //     })
                //     ->visible(fn (GayApplication $record): bool => $record->status_id == 1),
                ViewAction::make()->label('Квитанцияни кориу')->url(fn ($record) => route('gay-application.view', ['record' => $record->id]))
            ])
            ->filters([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('markCompleted')
                        ->label('Тестке кириу')
                        ->icon('fas-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            // Tanlangan barcha yozuvlar statusini tekshirish
                            if ($records->every(fn ($record) => $record->status_id == 2)) {
                                foreach ($records as $record) {
                                    $record->update(['status_id' => 3]); // 3 - completed
                                }
                    
                                Notification::make()
                                    ->title('Statuslar muvaffaqiyatli yangilandi!')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Faqat Active (2) statusdagi yozuvlarni tanlang!')
                                    ->danger()
                                    ->send();
                            }
                        }),
                        BulkAction::make('markActive')
                            ->label('Активлестириу')
                            ->icon('fas-check')
                            ->color('primary')
                            ->requiresConfirmation()
                            ->action(function (Collection $records) {
                                // Tanlangan barcha yozuvlar statusini tekshirish
                                if ($records->every(fn ($record) => $record->status_id == 1)) {
                                    foreach ($records as $record) {
                                        $record->update(['status_id' => 2]); // 2 - active
                                        $lastQueueNumber = QueueNumber::max('queue_number'); // Oxirgi navbat raqamini olamiz
                                        $nextQueueNumber = $lastQueueNumber + 1; // Keyingi raqamni olish
                                
                                        // Yangi navbat raqamini yaratish
                                        QueueNumber::create([
                                            'customer_id' => $record->customer_id,
                                            'gay_application_id' => $record->id,
                                            'queue_number' => $nextQueueNumber,
                                        ]);
                                        // Foydalanuvchiga navbat raqami yuborish
                                        $customer = Customer::find($record->customer_id);
                                        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                                        $telegram->sendMessage([
                                            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                                            'text' => "✅ Сизиң дизимнен өтиў сораўыңыз тастыйықланды!\n\nНәўбет номериңиз: $nextQueueNumber",
                                        ]);
                                    }
                        
                                    Notification::make()
                                        ->title('Statuslar muvaffaqiyatli yangilandi!')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Faqat Active (1) statusdagi yozuvlarni tanlang!')
                                        ->danger()
                                        ->send();
                                }
                            }),
                    BulkAction::make('markCancelled')
                        ->label('Отменён')
                        ->icon('fas-xmark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            if ($records->every(fn ($record) => $record->status_id == 1)) {
                                foreach ($records as $record) {
                                    $record->update(['status_id' => 4]); // 4 - active
                            
                                    // Foydalanuvchiga navbat raqami yuborish
                                    $customer = Customer::find($record->customer_id);
                                    $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                                    $telegram->sendMessage([
                                        'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                                        'text' => "❌ Сизиң дизимнен өтиў сораўыңыз бийкар етилди!",
                                    ]);
                                }
                    
                                Notification::make()
                                    ->title('Statuslar muvaffaqiyatli yangilandi!')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Faqat Active (1) statusdagi yozuvlarni tanlang!')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    BulkAction::make('markNotArrived')
                        ->label('Тестке келмеди')
                        ->icon('fas-xmark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            if ($records->every(fn ($record) => $record->status_id == 2)) {
                                foreach ($records as $record) {
                                    $record->update(['status_id' => 5]); // 2 - active
                            
                                    // Foydalanuvchiga navbat raqami yuborish
                                    $customer = Customer::find($record->customer_id);
                                    $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                                    $telegram->sendMessage([
                                        'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                                        'text' => "❌ тестке келмегенинз ушын наубет номериниз бийкар етилди!",
                                    ]);
                                }
                    
                                Notification::make()
                                    ->title('Statuslar muvaffaqiyatli yangilandi!')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Faqat Active (2) statusdagi yozuvlarni tanlang!')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
                
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getNavigationLabel(): string
    {
        return 'Очередь '; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Очередь'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Очередь '; // Rus tilidagi ko'plik shakli
    }
    // public static function canCreate():bool
    // {
    //     return  false; // Rus tilidagi ko'plik shakli
    // }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGayApplications::route('/'),
            'create' => Pages\CreateGayApplication::route('/create'),
            'edit' => Pages\EditGayApplication::route('/{record}/edit'),
        ];
    }
}
