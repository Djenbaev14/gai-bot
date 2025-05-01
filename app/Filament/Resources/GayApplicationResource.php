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
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Enums\ActionsPosition;
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
                    ->where('branch_id',auth()->user()->branch_id)
                    ->where('status_id',1) 
            )
            ->columns([
                Tables\Columns\ImageColumn::make('document_path')
                ->label('Квитанция')
                ->simpleLightbox(fn ($record) =>  $record?->document_path ?? "Your Image Url address", defaultDisplayUrl: true),
                TextColumn::make('branch.name')
                    ->label('Филиаль')
                    ->searchable(),
                TextColumn::make('customer.full_name')
                    ->label('ФИО')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i'), // Misol: 29.04.2025 15:42
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
                        
                        $lastQueueNumber = QueueNumber::where('branch_id', $record->branch_id)->max('queue_number');
                    
                        $myQueueNumber = $lastQueueNumber + 1;
                
                        // Yangi navbat raqamini yaratish
                        QueueNumber::create([
                            'user_id' => auth()->user()->id,
                            'customer_id' => $record->customer_id,
                            'gay_application_id' => $record->id,
                            'branch_id' => $record->branch_id,
                            'queue_number' => $myQueueNumber,
                        ]);
                        // Foydalanuvchiga navbat raqami yuborish
                        $customer = Customer::find($record->customer_id);
                        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

                        $lastQueue = GayApplication::where('branch_id', $record->branch_id)
                            ->whereHas('status', function (Builder $query) {
                                $query->where('key', '=', 'completed');
                            })->latest()->first();
                        $lastQueueNumber = $lastQueue?->queueNumber?->queue_number ?? 0;

                        $waitingCount = GayApplication::where('branch_id', $record->branch_id)
                            ->whereHas('status', function (Builder $query) {
                                $query->where('key', '=','active');
                            })->whereHas('queueNumber', function (Builder $query) use ($lastQueueNumber, $myQueueNumber) {
                                $query->where('queue_number', '>', $lastQueueNumber)
                                    ->where('queue_number', '<', $myQueueNumber);
                            })->count();

                        $waiting=$waitingCount>0 ? "❇️ Сиздиң алдыңызда $waitingCount пуҳара бар": "Сиздиң алдыңызда ешким жок";
                        $lastQueueText=$lastQueueNumber>0 ? "✅ Ақырғы кирген наўбет:  № $lastQueueNumber": "Еле ешким тестке кирген жок";
            
                        $telegram->sendMessage([
                            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                            'text' => "📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nТест тапсырыу орныныз: $record->branch_name\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,3,5 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy",
                        ]);

                        Notification::make()
                            ->title('Дизимнен өтиў сораўыңыз тастыйықланды')
                            ->success()
                            ->send();
                    })
                    ->after(function ($record, $livewire) {
                        $livewire->dispatch('refresh');
                    })
                    ->visible(fn (GayApplication $record): bool => $record->status_id == 1),
                Action::make('cancelled')
                    ->label('')
                    ->color('danger')
                    ->button()
                    ->icon('fas-circle-xmark')
                    ->form([
                        Textarea::make('comment')
                        ->label('Бийкар кылыу себеби')
                        ->default('📷 Айдаўшылық гүўалығын алыў ушын төленген квитанция, экзамен билети ямаса басқа тастыйықлаўшы хужжетти  жибериң. Паспорт ид карта жибермен❌')
                        ->required()
                        ->maxLength(500),
                    ])
                    ->action(function (array $data,GayApplication $record) {
                        $record->update(['status_id' => 4]); // 4 - active
                            
                        $customer = Customer::find($record->customer_id);
                        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                        $telegram->sendMessage([
                            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                            'text' => "❌ Сизиң дизимнен өтиў сораўыңыз бийкар етилди!\n\n".$data['comment'],
                        ]);
                            Notification::make()
                            ->title('Сизиң дизимнен өтиў сораўыңыз бийкар етилди')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (GayApplication $record): bool => $record->status_id == 1),
                ],position: ActionsPosition::BeforeColumns)
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
                                            'user_id' => auth()->user()->id,
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
                                            'text' => "📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,3,5 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy",
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
        return (string) GayApplication::where('branch_id',auth()->user()->branch_id)->where('status_id', 1)->count();
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
