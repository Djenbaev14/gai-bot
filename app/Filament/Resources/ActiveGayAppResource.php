<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActiveGayAppResource\Pages;
use App\Filament\Resources\ActiveGayAppResource\RelationManagers;
use App\Models\ActiveGayApp;
use App\Models\Customer;
use App\Models\GayApplication;
use App\Models\QueueNumber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;
use Filament\Tables\Actions\Action;
class ActiveGayAppResource extends Resource
{
    protected static ?string $model = GayApplication::class;
    protected static ?string $navigationGroup = 'Очередь';
    protected static ?int $navigationSort = 1;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->query(
                GayApplication::query()
                    ->where('gay_applications.branch_id',auth()->user()->branch_id)
                    ->where('status_id', 2)
                    ->join('queue_numbers', 'queue_numbers.gay_application_id', '=', 'gay_applications.id')
                    ->orderBy('queue_numbers.queue_number', 'asc')
                    ->select('gay_applications.*')
            )
            ->columns([
                ImageColumn::make('document_path')
                    ->label('Квитанция')
                    ->simpleLightbox(fn ($record) =>  $record?->document_path ?? "Your Image Url address", defaultDisplayUrl: true),
                TextColumn::make('queueNumber.queue_number')
                    ->label('Номер')
                    ->searchable(),
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
            ->defaultPaginationPageOption(25)
            ->actions([
                Action::make('active')
                    ->label('')
                    ->button()
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => "$record->queue_number_value номер акыргы кирди дурыспа ?")
                    // ->modalSubheading('Rostan ham ushbu amalni bajarishni xohlaysizmi?')
                    ->modalButton('Ауа, тастыйклайман')
                    ->icon('fas-flag')
                    ->action(function (GayApplication $record) {
                        $queueNumber = QueueNumber::where('gay_application_id', $record->id)->first();

                        if (!$queueNumber) {
                            return;
                        }

                        // 1 dan hozirgi navbat raqamigacha bo'lgan queueNumber'larni olamiz
                        $previousQueues = QueueNumber::where('queue_number', '<=', $queueNumber->queue_number)
                            ->join('gay_applications', 'queue_numbers.gay_application_id', '=', 'gay_applications.id')
                            ->where('gay_applications.status_id', 2) // faqat active
                            ->where('gay_applications.branch_id', $record->branch_id) // faqat bir xil branch
                            ->pluck('queue_numbers.gay_application_id');

                        // Ular orqali tegishli arizalarni 'completed' statusiga o'zgartiramiz
                        GayApplication::whereIn('id', $previousQueues)
                            ->update(['status_id' => 3]); // bu yerda 3 — 'completed' bo'lishi kerak

                    }),
                Action::make('cancelled')
                        ->label('')
                        ->color('danger')
                        ->button()
                        ->requiresConfirmation()
                        ->modalHeading(fn ($record) => "$record->queue_number_value номер ди бийкарлайжаксызба ?")
                        // ->modalSubheading('Rostan ham ushbu amalni bajarishni xohlaysizmi?')
                        ->modalButton('Ауа, тастыйклайман')
                        ->icon('fas-circle-xmark')
                        ->action(function (GayApplication $record) {
                            
                            $record->update(['status_id' => 5]); // 2 - active
                            
                            $customer = Customer::find($record->customer_id);
                            $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
                            $telegram->sendMessage([
                                'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
                                'text' => "❌ Имтиханға келмегениңиз  ушын наўбет  бийкар етилди, қайталдан наўбет алың!",
                            ]);
                                Notification::make()
                                ->title('❌ Имтиханға келмегениңиз  ушын наўбет  бийкар етилди')
                                ->success()
                                ->send();
                        }),
                // ViewAction::make()->label('Квитанцияни кориу')->url(fn ($record) => route('gay-application.view', ['record' => $record->id]))
                    ], position: ActionsPosition::BeforeColumns)
            ->filters([
                //
            ]);
            // ->bulkActions([
            //     Tables\Actions\BulkActionGroup::make([
            //         BulkAction::make('markCompleted')
            //             ->label('Тестке кириу')
            //             ->icon('fas-check')
            //             ->color('success')
            //             ->requiresConfirmation()
            //             ->action(function (Collection $records) {
            //                 // Tanlangan barcha yozuvlar statusini tekshirish
            //                 if ($records->every(fn ($record) => $record->status_id == 2)) {
            //                     foreach ($records as $record) {
            //                         $record->update(['status_id' => 3]); // 3 - completed
            //                     }
                    
            //                     Notification::make()
            //                         ->title('Statuslar muvaffaqiyatli yangilandi!')
            //                         ->success()
            //                         ->send();
            //                 } else {
            //                     Notification::make()
            //                         ->title('Faqat Active (2) statusdagi yozuvlarni tanlang!')
            //                         ->danger()
            //                         ->send();
            //                 }
            //             }),
            //         BulkAction::make('markNotArrived')
            //             ->label('Тестке келмеди')
            //             ->icon('fas-xmark')
            //             ->color('danger')
            //             ->requiresConfirmation()
            //             ->action(function (Collection $records) {
            //                 if ($records->every(fn ($record) => $record->status_id == 2)) {
            //                     foreach ($records as $record) {
            //                         $record->update(['status_id' => 5]); // 2 - active
                            
            //                         // Foydalanuvchiga navbat raqami yuborish
            //                         $customer = Customer::find($record->customer_id);
            //                         $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
            //                         $telegram->sendMessage([
            //                             'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
            //                             'text' => "❌ Имтиханға келмегениңиз  ушын наўбет  бийкар етилди, қайталдан наўбет алың!",
            //                         ]);
            //                     }
                    
            //                     Notification::make()
            //                         ->title('Statuslar muvaffaqiyatli yangilandi!')
            //                         ->success()
            //                         ->send();
            //                 } else {
            //                     Notification::make()
            //                         ->title('Faqat Active (2) statusdagi yozuvlarni tanlang!')
            //                         ->danger()
            //                         ->send();
            //                 }
            //             }),
            //     ]),
                
            // ]);
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) GayApplication::where('branch_id',auth()->user()->branch_id)->where('status_id', 2)->count();
    }
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary'; // yoki success, danger, primary
    }
    public static function getNavigationLabel(): string
    {
        return 'Актив'; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Актив'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Актив'; // Rus tilidagi ko'plik shakli
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActiveGayApps::route('/'),
            'create' => Pages\CreateActiveGayApp::route('/create'),
            'edit' => Pages\EditActiveGayApp::route('/{record}/edit'),
        ];
    }
}
