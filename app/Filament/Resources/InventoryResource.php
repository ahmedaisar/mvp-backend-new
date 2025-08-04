<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory;
use App\Models\RatePlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationGroup = 'Pricing & Revenue';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Inventory Management')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Inventory Details')
                            ->schema([
                                Forms\Components\Section::make('Room Availability')
                                    ->schema([
                                        Forms\Components\Select::make('rate_plan_id')
                                            ->label('Rate Plan')
                                            ->relationship('ratePlan', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (RatePlan $record) => "{$record->roomType->resort->name} | {$record->roomType->name} | {$record->name}")
                                            ->getSearchResultsUsing(function (string $search): array {
                                                return RatePlan::query()
                                                    ->with(['roomType.resort'])
                                                    ->where(function ($query) use ($search) {
                                                        $query->whereHas('roomType.resort', function ($q) use ($search) {
                                                            $q->where('name', 'like', "%{$search}%");
                                                        })
                                                        ->orWhereHas('roomType', function ($q) use ($search) {
                                                            $q->where('name', 'like', "%{$search}%");
                                                        })
                                                        ->orWhere('name', 'like', "%{$search}%");
                                                    })
                                                    ->get()
                                                    ->mapWithKeys(fn ($ratePlan) => [
                                                        $ratePlan->id => "{$ratePlan->roomType->resort->name} | {$ratePlan->roomType->name} | {$ratePlan->name}"
                                                    ])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpanFull(),
                                            
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label('Start Date')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->minDate(now()),
                                            
                                        Forms\Components\DatePicker::make('end_date')
                                            ->label('End Date')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->minDate(fn (callable $get) => $get('start_date'))
                                            ->afterOrEqual('start_date')
                                            ->rules([
                                                function (callable $get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        $ratePlanId = $get('rate_plan_id');
                                                        $startDate = $get('start_date');
                                                        $endDate = $value;
                                                        $recordId = request()->route('record');
                                                        
                                                        if (!$ratePlanId || !$startDate || !$endDate) {
                                                            return;
                                                        }
                                                        
                                                        $query = \App\Models\Inventory::where('rate_plan_id', $ratePlanId)
                                                            ->where(function ($query) use ($startDate, $endDate) {
                                                                // Check for overlapping date ranges
                                                                $query->where(function ($q) use ($startDate, $endDate) {
                                                                    // New start date falls within existing range
                                                                    $q->where('start_date', '<=', $startDate)
                                                                      ->where('end_date', '>=', $startDate);
                                                                })->orWhere(function ($q) use ($startDate, $endDate) {
                                                                    // New end date falls within existing range
                                                                    $q->where('start_date', '<=', $endDate)
                                                                      ->where('end_date', '>=', $endDate);
                                                                })->orWhere(function ($q) use ($startDate, $endDate) {
                                                                    // New range completely contains existing range
                                                                    $q->where('start_date', '>=', $startDate)
                                                                      ->where('end_date', '<=', $endDate);
                                                                });
                                                            });
                                                        
                                                        // Exclude current record when editing
                                                        if ($recordId) {
                                                            $query->where('id', '!=', $recordId);
                                                        }
                                                        
                                                        $exists = $query->exists();
                                                        
                                                        if ($exists) {
                                                            $fail("An inventory record already exists for this rate plan within the selected date range.");
                                                        }
                                                    };
                                                },
                                            ]),
                                            
                                        Forms\Components\TextInput::make('available_rooms')
                                            ->label('Available Rooms')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->helperText('Number of rooms available for this date'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Status')
                                    ->schema([
                                        Forms\Components\Toggle::make('blocked')
                                            ->label('Blocked')
                                            ->helperText('Block all inventory for this date (overrides available rooms)')
                                            ->default(false),
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
                Tables\Columns\TextColumn::make('ratePlan.name')
                    ->label('Rate Plan')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('ratePlan.roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('ratePlan.roomType.resort.name')
                    ->label('Resort')
                    ->searchable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d M Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d M Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('available_rooms')
                    ->label('Available Rooms')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 5 ? 'success' : ($state > 0 ? 'warning' : 'danger')),
                    
                Tables\Columns\IconColumn::make('blocked')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn ($record) => $record->blocked ? 'Blocked' : 'Available'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rate_plan_id')
                    ->label('Rate Plan')
                    ->relationship('ratePlan', 'name')
                    ->searchable()
                    ->preload(),
                    
                TernaryFilter::make('blocked')
                    ->label('Status')
                    ->placeholder('All inventory')
                    ->trueLabel('Blocked only')
                    ->falseLabel('Available only'),
                    
                Filter::make('low_inventory')
                    ->label('Low Inventory (≤ 5 rooms)')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('available_rooms', '<=', 5)
                              ->where('blocked', false)
                    )
                    ->toggle(),
                    
                Filter::make('no_inventory')
                    ->label('No Inventory')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('available_rooms', 0)
                              ->where('blocked', false)
                    )
                    ->toggle(),
                    
                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->where(function ($query) use ($date) {
                                    $query->where('start_date', '>=', $date)
                                        ->orWhere('end_date', '>=', $date);
                                }),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->where(function ($query) use ($date) {
                                    $query->where('start_date', '<=', $date)
                                        ->orWhere('end_date', '<=', $date);
                                }),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('block_inventory')
                        ->label('Block Selected')
                        ->icon('heroicon-o-no-symbol')
                        ->action(fn ($records) => $records->each->update(['blocked' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('unblock_inventory')
                        ->label('Unblock Selected')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['blocked' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Inventory Details')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Inventory Information')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('ratePlan.name')
                                            ->label('Rate Plan')
                                            ->badge()
                                            ->color('primary'),
                                        Infolists\Components\TextEntry::make('ratePlan.roomType.name')
                                            ->label('Room Type')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('ratePlan.roomType.resort.name')
                                            ->label('Resort')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('start_date')
                                            ->label('Start Date')
                                            ->date('d M Y'),
                                        Infolists\Components\TextEntry::make('end_date')
                                            ->label('End Date')
                                            ->date('d M Y'),
                                        Infolists\Components\TextEntry::make('available_rooms')
                                            ->label('Available Rooms')
                                            ->badge()
                                            ->color(fn ($state) => $state > 5 ? 'success' : ($state > 0 ? 'warning' : 'danger')),
                                        Infolists\Components\IconEntry::make('blocked')
                                            ->label('Status')
                                            ->boolean(),
                                    ])
                                    ->columns(2),
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
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'view' => Pages\ViewInventory::route('/{record}'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
