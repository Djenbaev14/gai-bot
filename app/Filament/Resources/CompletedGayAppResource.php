<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompletedGayAppResource\Pages;
use App\Filament\Resources\CompletedGayAppResource\RelationManagers;
use App\Models\CompletedGayApp;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;

class CompletedGayAppResource extends Resource
{
    protected static ?string $model = GayApplication::class;
    protected static ?string $navigationGroup = 'Очередь';

    protected static ?int $navigationSort = 3;

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
                    ->where('status_id',3) 
            )
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
                TextColumn::make('customer.phone_number')
                    ->label('Телефон')
                    ->searchable(),
            ])
            ->defaultSort('created_at','asc')
            ->defaultPaginationPageOption(25)
            ->filters([
                //
            ]);
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) GayApplication::where('status_id', 3)->count();
    }
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success'; // yoki success, danger, primary
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getNavigationLabel(): string
    {
        return 'Завершенный'; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Завершенный'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Завершенный'; // Rus tilidagi ko'plik shakli
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompletedGayApps::route('/'),
            'create' => Pages\CreateCompletedGayApp::route('/create'),
            'edit' => Pages\EditCompletedGayApp::route('/{record}/edit'),
        ];
    }
}
