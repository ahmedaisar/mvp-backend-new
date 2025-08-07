<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Transfer;
use App\Models\Resort;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static ?string $navigationGroup = 'Services';

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Transfer Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Details')
                            ->schema([
                                Forms\Components\Section::make('Transfer Information')
                                    ->schema([
                                        Forms\Components\Select::make('resort_id')
                                            ->label('Resort')
                                            ->relationship('resort', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                            
                                        Forms\Components\TextInput::make('name')
                                            ->label('Transfer Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Seaplane Transfer, Speedboat Transfer'),
                                            
                                        Forms\Components\Select::make('type')
                                            ->required()
                                            ->options([
                                                'seaplane' => 'Seaplane',
                                                'speedboat' => 'Speedboat',
                                                'domestic_flight' => 'Domestic Flight',
                                                'bus' => 'Bus',
                                                'car' => 'Car',
                                                'helicopter' => 'Helicopter',
                                            ])
                                            ->placeholder('Select transfer type'),
                                            
                                        Forms\Components\TextInput::make('route')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Male Airport - Resort'),
                                            
                                        Forms\Components\TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            ->prefix('USD')
                                            ->minValue(0),
                                            
                                        Forms\Components\TextInput::make('capacity')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(50)
                                            ->helperText('Maximum number of passengers'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Description')
                                    ->schema([
                                        Forms\Components\RichEditor::make('description')
                                            ->label('Description')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'bulletList',
                                                'orderedList',
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Settings')
                            ->schema([
                                Forms\Components\Section::make('Transfer Status')
                                    ->schema([
                                        Forms\Components\Toggle::make('active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Only active transfers are available for booking'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
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
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Transfer Name')
                    ->searchable()
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'seaplane' => 'info',
                        'speedboat' => 'success',
                        'domestic_flight' => 'warning',
                        'helicopter' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('route')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->sortable()
                    ->suffix(' pax'),
                    
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
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('resort_id')
                    ->label('Resort')
                    ->relationship('resort', 'name')
                    ->searchable()
                    ->preload(),
                    
                SelectFilter::make('type')
                    ->options([
                        'seaplane' => 'Seaplane',
                        'speedboat' => 'Speedboat',
                        'domestic_flight' => 'Domestic Flight',
                        'bus' => 'Bus',
                        'car' => 'Car',
                        'helicopter' => 'Helicopter',
                    ]),
                    
                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All transfers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-m-square-2-stack')
                    ->color('gray')
                    ->action(function (Transfer $record): void {
                        $newTransfer = $record->replicate();
                        $newTransfer->name = $record->name . ' (Copy)';
                        $newTransfer->active = false;
                        $newTransfer->save();
                    }),
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Transfer Details')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Transfer Information')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('resort.name')
                                            ->label('Resort')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('name')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('type')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('route')
                                            ->icon('heroicon-o-map-pin'),
                                        Infolists\Components\TextEntry::make('price')
                                            ->money('USD'),
                                        Infolists\Components\TextEntry::make('capacity')
                                            ->suffix(' passengers'),
                                        Infolists\Components\IconEntry::make('active')
                                            ->label('Status')
                                            ->boolean(),
                                    ])
                                    ->columns(2),
                                    
                                Infolists\Components\Section::make('Description')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('description')
                                            ->label('Description')
                                            ->prose()
                                            ->hiddenLabel(),
                                    ])
                                    ->collapsible(),
                            ]),
                    ])
                    ->columnSpanFull(),
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
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'view' => Pages\ViewTransfer::route('/{record}'),
            'edit' => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
