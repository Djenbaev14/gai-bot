<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChangeGayApplicationResource\Pages;
use App\Filament\Resources\ChangeGayApplicationResource\RelationManagers;
use App\Models\ChangeGayApplication;
use App\Models\GayApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChangeGayApplicationResource extends Resource
{
    protected static ?string $model = GayApplication::class;

    protected static ?int $navigationSort = 4;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                    ->whereHas('queueNumber')
            )
            ->columns([
                ImageColumn::make('document_path')
                    ->label('Квитанция')
                    ->simpleLightbox(fn ($record) =>  $record?->document_path ?? "Your Image Url address", defaultDisplayUrl: true),
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
        return (string) GayApplication::where('branch_id',auth()->user()->branch_id)->whereHas('queueNumber')->where('status_id', 4)->count();
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
        return 'Изменившие место теста'; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Изменившие место теста'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Изменившие место теста'; // Rus tilidagi ko'plik shakli
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangeGayApplications::route('/'),
            'create' => Pages\CreateChangeGayApplication::route('/create'),
            'edit' => Pages\EditChangeGayApplication::route('/{record}/edit'),
        ];
    }
}
