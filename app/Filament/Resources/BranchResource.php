<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers;
use App\Models\Branch;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'fas-code-branch';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                ->schema([
                    TextInput::make('name')->label('Название')->placeholder('Название')->unique(ignoreRecord: true)->required()->columnSpan(12),
                    Select::make('regions')
                        ->label('Hududlar')
                        ->multiple()
                        ->relationship('regions', 'name')
                        ->options(function () {
                            // Boshqa branchlarga biriktirilmagan regionlar
                            return Region::whereDoesntHave('branchRegions')->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->columnSpan(12),
                ])->columns(12)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Filial nomi')
                    ->searchable()
                    ->sortable(),
    
                TextColumn::make('regions.name')
                    ->label('Hududlar')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(), // ustunni yashirish/ko‘rsatish imkoniyati
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function getNavigationLabel(): string
    {
        return 'Филиалы'; // Rus tilidagi nom
    }
    public static function getModelLabel(): string
    {
        return 'Филиалы'; // Rus tilidagi yakka holdagi nom
    }
    public static function getPluralModelLabel(): string
    {
        return 'Филиалы'; // Rus tilidagi ko'plik shakli
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
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
