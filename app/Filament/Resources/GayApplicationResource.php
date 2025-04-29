<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GayApplicationResource\Pages;
use App\Filament\Resources\GayApplicationResource\RelationManagers;
use App\Models\Customer;
use App\Models\GayApplication;
use App\Models\QueueNumber;
use App\Models\Status;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
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

    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Очередь';
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
            ->query(
                GayApplication::query()
                    ->where('status_id',1) 
            )
            ->columns([
                Tables\Columns\ImageColumn::make('document_path')
    ->simpleLightbox(fn ($record) =>  $record?->document_path ?? "Your Image Url address", defaultDisplayUrl: true),
                TextColumn::make('customer.full_name')
                    ->label('ФИО')
                    ->searchable(),
                TextColumn::make('customer.passport')
                    ->label('Паспорт')
                    ->searchable(),
                TextColumn::make('customer.phone_number')
                    ->label('Телефон')
                    ->searchable(),
                // TextColumn::make('status.name')
                //     ->label('Статус'),
                    
                // TextColumn::make('status.name')
                // ->label('Статус')
                // ->html()
                // ->formatStateUsing(function ($state, $record) {
                //     $color = $record->status->color ?? '#999';
                //     return "<span style='
                //         background-color: {$color};
                //         color: white;
                //         padding: 4px 8px;
                //         border-radius: 8px;
                //         font-size: 12px;
                //         display: inline-block;
                //     '>" . ucfirst($state) . "</span>";
                // }),
            ])
            ->defaultSort('created_at','asc')
            ->defaultPaginationPageOption(25)
            ->actions([
                Action::make('active')
                    ->label('')
                    ->button()
                    ->color('success')
                    ->icon('fas-circle-check')
                    ->action(function (GayApplication $record) {
                        
                        $record->update(['status_id' => 2]); // 2 - active
                        $lastQueueNumber = QueueNumber::max('queue_number'); // Oxirgi navbat raqamini olamiz
                        $myQueueNumber = $lastQueueNumber + 1; // Keyingi raqamni olish
                
                        // Yangi navbat raqamini yaratish
                        QueueNumber::create([
                            'customer_id' => $record->customer_id,
                            'gay_application_id' => $record->id,
                            'queue_number' => $myQueueNumber,
                        ]);
                        // Foydalanuvchiga navbat raqami yuborish
                        $customer = Customer::find($record->customer_id);
                        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                        $lastQueue = GayApplication::whereHas('status', function (Builder $query) {
                            $query->where('key', '=', 'completed');
                        })->latest()->first();
                        $lastQueueNumber = $lastQueue?->queueNumber?->queue_number ?? 0;

                        $waitingCount = GayApplication::whereHas('status', function (Builder $query) {
                            $query->where('key', '=','active');
                        })->whereHas('queueNumber', function (Builder $query) use ($lastQueueNumber, $myQueueNumber) {
                            $query->where('queue_number', '>', $lastQueueNumber)
                                  ->where('queue_number', '<', $myQueueNumber);
                        })->count();

                        $waiting=$waitingCount>0 ? "❇️ Сиздиң алдыңызда $waitingCount пуҳара бар": "Сиздиң алдыңызда ешким жок";
                        $lastQueueText=$lastQueueNumber>0 ? "✅ Ақырғы кирген наўбет:  № $lastQueueNumber": "Еле ешким тестке кирген жок";
            
                        $telegram->sendMessage([
                            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                            'text' => "📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,2,3 күнлери болып өтеди",
                        ]);

                        Notification::make()
                            ->title('Дизимнен өтиў сораўыңыз тастыйықланды')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (GayApplication $record): bool => $record->status_id == 1),
                Action::make('cancelled')
                    ->label('')
                    ->color('danger')
                    ->button()
                    ->icon('fas-circle-xmark')
                    ->action(function (GayApplication $record) {
                        $record->update(['status_id' => 4]); // 4 - active
                            
                        $customer = Customer::find($record->customer_id);
                        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                        $telegram->sendMessage([
                            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                            'text' => "❌ Сизиң дизимнен өтиў сораўыңыз бийкар етилди!",
                        ]);
                            Notification::make()
                            ->title('Сизиң дизимнен өтиў сораўыңыз бийкар етилди')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (GayApplication $record): bool => $record->status_id == 1),
                ViewAction::make()->label('Квитанцияни кориу')->url(fn ($record) => route('gay-application.view', ['record' => $record->id]))
            ])
            ->filters([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
                                        $myQueueNumber = $lastQueueNumber + 1; // Keyingi raqamni olish
                                
                                        // Yangi navbat raqamini yaratish
                                        QueueNumber::create([
                                            'customer_id' => $record->customer_id,
                                            'gay_application_id' => $record->id,
                                            'queue_number' => $myQueueNumber,
                                        ]);
                                        // Foydalanuvchiga navbat raqami yuborish
                                        $customer = Customer::find($record->customer_id);
                                        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                                        $lastQueue = GayApplication::whereHas('status', function (Builder $query) {
                                            $query->where('key', '=', 'completed');
                                        })->latest()->first();
                                        $lastQueueNumber = $lastQueue?->queueNumber?->queue_number ?? 0;

                                        $waitingCount = GayApplication::whereHas('status', function (Builder $query) {
                                            $query->where('key', '=','active');
                                        })->whereHas('queueNumber', function (Builder $query) use ($lastQueueNumber, $myQueueNumber) {
                                            $query->where('queue_number', '>', $lastQueueNumber)
                                                  ->where('queue_number', '<', $myQueueNumber);
                                        })->count();

                                        $waiting=$waitingCount>0 ? "❇️ Сиздиң алдыңызда $waitingCount пуҳара бар": "Сиздиң алдыңызда ешким жок";
                                        $lastQueueText=$lastQueueNumber>0 ? "✅ Ақырғы кирген наўбет:  № $lastQueueNumber": "Еле ешким тестке кирген жок";
                            
                                        $telegram->sendMessage([
                                            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                                            'text' => "📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,2,3 күнлери болып өтеди",
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
                ]),
                
            ]);
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) GayApplication::where('status_id', 1)->count();
    }
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning'; // yoki success, danger, primary
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getNavigationLabel(): string
    {
        return 'Ожидает подтверждение'; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Ожидает подтверждение'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Ожидает подтверждение'; // Rus tilidagi ko'plik shakli
    }
    public static function canCreate():bool
    {
        return  false; // Rus tilidagi ko'plik shakli
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGayApplications::route('/'),
            'create' => Pages\CreateGayApplication::route('/create'),
            'edit' => Pages\EditGayApplication::route('/{record}/edit'),
        ];
    }
}
