<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CancelledAppResource\Pages;
use App\Filament\Resources\CancelledAppResource\RelationManagers;
use App\Models\CancelledApp;
use App\Models\GayApplication;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CancelledAppResource extends Resource
{
    protected static ?string $model = GayApplication::class;
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = 'Очередь';

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
                    ->where('branch_id',auth()->user()->branch_id)
                    ->where('status_id',4) 
                    ->whereDoesntHave('queueNumber')
            )
            ->columns([
                ImageColumn::make('document_path')
                    ->label('Квитанция')
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
            ])
            ->defaultSort('created_at','asc')
            ->defaultPaginationPageOption(25)
            
            ->filters([
                //
            ]);
    }
    public static function getNavigationBadge(): ?string
    {
        return (string) GayApplication::where('branch_id',auth()->user()->branch_id)->whereDoesntHave('queueNumber')->where('status_id', 4)->count();
    }
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger'; // yoki success, danger, primary
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getNavigationLabel(): string
    {
        return 'Отмененный'; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Отмененный'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Отмененный'; // Rus tilidagi ko'plik shakli
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCancelledApps::route('/'),
            'create' => Pages\CreateCancelledApp::route('/create'),
            'edit' => Pages\EditCancelledApp::route('/{record}/edit'),
        ];
    }
}
