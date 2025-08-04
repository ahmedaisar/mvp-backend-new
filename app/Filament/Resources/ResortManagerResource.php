<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResortManagerResource\Pages;
use App\Models\ResortManager;
use App\Models\Resort;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResortManagerResource extends Resource
{
    protected static ?string $model = ResortManager::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->options(function () {
                        // Get all users with role 'resort_manager'
                        return User::where('role', 'resort_manager')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),
                
                Forms\Components\Select::make('resort_id')
                    ->label('Resort')
                    ->options(Resort::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Manager')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('resort.name')
                    ->label('Resort')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('resort_id')
                    ->label('Resort')
                    ->options(Resort::pluck('name', 'id'))
                    ->searchable(),
                
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Manager')
                    ->options(User::where('role', 'resort_manager')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListResortManagers::route('/'),
            'create' => Pages\CreateResortManager::route('/create'),
            'edit' => Pages\EditResortManager::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'resort']);
    }
}
