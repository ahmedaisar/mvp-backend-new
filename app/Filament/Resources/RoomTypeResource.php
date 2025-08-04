<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomTypeResource\Pages;
use App\Filament\Resources\RoomTypeResource\RelationManagers;
use App\Models\RoomType;
use App\Models\Resort;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Property Management';

    protected static ?int $navigationSort = 2;    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room Type Information')
                    ->schema([
                        Forms\Components\Select::make('resort_id')
                            ->label('Resort')
                            ->relationship('resort', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\TextInput::make('code')
                            ->label('Room Code')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., DELUXE-001'),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Room Type Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Deluxe Ocean View'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Capacity')
                    ->schema([
                        Forms\Components\TextInput::make('capacity_adults')
                            ->label('Adult Capacity')
                            ->required()
                            ->numeric()
                            ->default(2)
                            ->minValue(1)
                            ->maxValue(10),
                            
                        Forms\Components\TextInput::make('capacity_children')
                            ->label('Children Capacity')
                            ->required()
                            ->numeric()
                            ->default(2)
                            ->minValue(0)
                            ->maxValue(6),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Pricing & Status')
                    ->schema([
                        Forms\Components\TextInput::make('default_price')
                            ->label('Default Price (USD)')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0),
                            
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active room types are available for booking'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Room Images')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->label('Room Photos')
                            ->multiple()
                            ->image()
                            ->directory('room-types')
                            ->maxFiles(10)
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('resort.name')
                    ->label('Resort')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Room Type')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('capacity_adults')
                    ->label('Adults')
                    ->alignCenter()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('capacity_children')
                    ->label('Children')
                    ->alignCenter()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('default_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('resort_id')
                    ->label('Resort')
                    ->relationship('resort', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All room types')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListRoomTypes::route('/'),
            'create' => Pages\CreateRoomType::route('/create'),
            'view' => Pages\ViewRoomType::route('/{record}'),
            'edit' => Pages\EditRoomType::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
