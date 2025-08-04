<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeasonalRateResource\Pages;
use App\Filament\Resources\SeasonalRateResource\RelationManagers;
use App\Models\SeasonalRate;
use App\Models\RatePlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SeasonalRateResource extends Resource
{
    protected static ?string $model = SeasonalRate::class;

    protected static ?string $navigationGroup = 'Pricing & Revenue';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Seasonal Rate')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Rate Details')
                            ->schema([
                                Forms\Components\Section::make('Rate Plan & Pricing')
                                    ->schema([
                                        Forms\Components\Select::make('rate_plan_id')
                                            ->label('Rate Plan')
                                            ->relationship('ratePlan', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpanFull(),
                                            
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label('Season Start Date')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\DatePicker::make('end_date')
                                            ->label('Season End Date')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->after('start_date'),
                                            
                                        Forms\Components\TextInput::make('nightly_price')
                                            ->label('Nightly Price')
                                            ->required()
                                            ->numeric()
                                            ->prefix('USD')
                                            ->minValue(0)
                                            ->step(0.01),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Stay Requirements')
                                    ->schema([
                                        Forms\Components\TextInput::make('min_stay')
                                            ->label('Minimum Stay (nights)')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1),
                                            
                                        Forms\Components\TextInput::make('max_stay')
                                            ->label('Maximum Stay (nights)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->helperText('Leave empty for no maximum limit'),
                                    ])
                                    ->columns(2),
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
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Season Start')
                    ->date('d M Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Season End') 
                    ->date('d M Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state . ' days')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('nightly_price')
                    ->label('Nightly Price')
                    ->money('USD')
                    ->sortable()
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\TextColumn::make('min_stay')
                    ->label('Min Stay')
                    ->numeric()
                    ->suffix(' nights')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('max_stay')
                    ->label('Max Stay')
                    ->numeric()
                    ->suffix(' nights')
                    ->placeholder('No limit')
                    ->sortable(),
                    
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
                    
                Filter::make('current_season')
                    ->label('Current Season')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereDate('start_date', '<=', now())
                              ->whereDate('end_date', '>=', now())
                    )
                    ->toggle(),
                    
                Filter::make('upcoming_season')
                    ->label('Upcoming Season')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereDate('start_date', '>', now())
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Seasonal Rate Details')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Rate Information')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('ratePlan.name')
                                            ->label('Rate Plan')
                                            ->badge()
                                            ->color('primary'),
                                        Infolists\Components\TextEntry::make('ratePlan.roomType.name')
                                            ->label('Room Type')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('start_date')
                                            ->label('Season Start')
                                            ->date('d M Y'),
                                        Infolists\Components\TextEntry::make('end_date')
                                            ->label('Season End')
                                            ->date('d M Y'),
                                        Infolists\Components\TextEntry::make('duration')
                                            ->label('Duration')
                                            ->formatStateUsing(fn ($state) => $state . ' days'),
                                        Infolists\Components\TextEntry::make('nightly_price')
                                            ->label('Nightly Price')
                                            ->money('USD'),
                                    ])
                                    ->columns(2),
                                    
                                Infolists\Components\Section::make('Stay Requirements')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('min_stay')
                                            ->label('Minimum Stay')
                                            ->formatStateUsing(fn ($state) => $state . ' nights'),
                                        Infolists\Components\TextEntry::make('max_stay')
                                            ->label('Maximum Stay')
                                            ->formatStateUsing(fn ($state) => $state ? $state . ' nights' : 'No limit'),
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
            'index' => Pages\ListSeasonalRates::route('/'),
            'create' => Pages\CreateSeasonalRate::route('/create'),
            'view' => Pages\ViewSeasonalRate::route('/{record}'),
            'edit' => Pages\EditSeasonalRate::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
