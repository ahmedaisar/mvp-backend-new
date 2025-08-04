<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RatePlanResource\Pages;
use App\Filament\Resources\RatePlanResource\RelationManagers;
use App\Models\RatePlan;
use App\Models\RoomType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;

class RatePlanResource extends Resource
{
    protected static ?string $model = RatePlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Pricing & Revenue';

    protected static ?int $navigationSort = 1;    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rate Plan Information')
                    ->schema([
                        Forms\Components\Select::make('room_type_id')
                            ->label('Room Type')
                            ->relationship('roomType', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return $record->resort->name . ' - ' . $record->name;
                            }),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Rate Plan Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Early Bird Special, Last Minute Deal'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Plan Features')
                    ->schema([
                        Forms\Components\Toggle::make('refundable')
                            ->label('Refundable')
                            ->default(true)
                            ->helperText('Can guests get a refund if they cancel?'),
                            
                        Forms\Components\Toggle::make('breakfast_included')
                            ->label('Breakfast Included')
                            ->default(false)
                            ->helperText('Does this rate include breakfast?'),
                            
                        Forms\Components\Toggle::make('deposit_required')
                            ->label('Deposit Required')
                            ->default(false)
                            ->reactive()
                            ->helperText('Is a deposit required to secure booking?'),
                            
                        Forms\Components\TextInput::make('deposit_percentage')
                            ->label('Deposit Percentage')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Forms\Get $get) => $get('deposit_required'))
                            ->required(fn (Forms\Get $get) => $get('deposit_required'))
                            ->helperText('Percentage of total booking amount required as deposit'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Cancellation Policy')
                    ->schema([
                        Forms\Components\RichEditor::make('cancellation_policy')
                            ->label('Cancellation Policy')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                            ])
                            ->columnSpanFull()
                            ->placeholder('Enter the cancellation policy details...'),
                    ]),
                    
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active rate plans are available for booking'),
                    ]),
                    
                Forms\Components\Section::make('Country Restrictions')
                    ->schema([
                        Forms\Components\Select::make('country_restriction_type')
                            ->label('Restriction Type')
                            ->options([
                                'none' => 'No Restrictions',
                                'include_only' => 'Include Only Selected Countries',
                                'exclude_only' => 'Exclude Selected Countries',
                            ])
                            ->default('none')
                            ->reactive()
                            ->helperText('Control which countries this rate plan is available for'),
                            
                        Forms\Components\Select::make('applicable_countries')
                            ->label('Available In Countries')
                            ->options(RatePlan::getCountriesList())
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('country_restriction_type') === 'include_only')
                            ->helperText('Rate plan will ONLY be available in these countries'),
                            
                        Forms\Components\Select::make('excluded_countries')
                            ->label('Excluded Countries')
                            ->options(RatePlan::getCountriesList())
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('country_restriction_type') === 'exclude_only')
                            ->helperText('Rate plan will NOT be available in these countries'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('roomType.resort.name')
                    ->label('Resort')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Rate Plan')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\IconColumn::make('refundable')
                    ->label('Refundable')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\IconColumn::make('breakfast_included')
                    ->label('Breakfast')
                    ->boolean()
                    ->trueIcon('heroicon-o-cake')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
                    
                Tables\Columns\IconColumn::make('deposit_required')
                    ->label('Deposit')
                    ->boolean()
                    ->trueIcon('heroicon-o-credit-card')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                    
                Tables\Columns\TextColumn::make('deposit_percentage')
                    ->label('%')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->placeholder('N/A'),
                    
                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('country_restriction_type')
                    ->label('Country Restrictions')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'none' => 'None',
                        'include_only' => 'Include Only',
                        'exclude_only' => 'Exclude Only',
                        default => 'None',
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'none' => 'gray',
                        'include_only' => 'success',
                        'exclude_only' => 'warning',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('applicable_countries')
                    ->label('Included Countries')
                   // ->visible(fn ($record) => $record->country_restriction_type === 'include_only')
                    ->formatStateUsing(fn ($record) => $record->getApplicableCountriesCount() . ' countries')
                    ->tooltip(function ($record) {
                        if (!$record->applicable_countries || count($record->applicable_countries) === 0) {
                            return 'No countries selected';
                        }
                        
                        $countries = collect($record->applicable_countries)
                            ->map(fn ($code) => RatePlan::getCountriesList()[$code] ?? $code)
                            ->take(10)
                            ->join(', ');
                            
                        $more = count($record->applicable_countries) > 10 
                            ? '... and ' . (count($record->applicable_countries) - 10) . ' more' 
                            : '';
                            
                        return $countries . $more;
                    }),
                    
                Tables\Columns\TextColumn::make('excluded_countries')
                    ->label('Excluded Countries')
                    //->visible(fn ($record) => $record->country_restriction_type === 'exclude_only')
                    ->formatStateUsing(fn ($record) => $record->getExcludedCountriesCount() . ' countries')
                    ->tooltip(function ($record) {
                        if (!$record->excluded_countries || count($record->excluded_countries) === 0) {
                            return 'No countries selected';
                        }
                        
                        $countries = collect($record->excluded_countries)
                            ->map(fn ($code) => RatePlan::getCountriesList()[$code] ?? $code)
                            ->take(10)
                            ->join(', ');
                            
                        $more = count($record->excluded_countries) > 10 
                            ? '... and ' . (count($record->excluded_countries) - 10) . ' more' 
                            : '';
                            
                        return $countries . $more;
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('room_type_id')
                    ->label('Room Type')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All rate plans')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                    
                Tables\Filters\TernaryFilter::make('refundable')
                    ->label('Refundable')
                    ->placeholder('All plans')
                    ->trueLabel('Refundable only')
                    ->falseLabel('Non-refundable only'),
                    
                Tables\Filters\TernaryFilter::make('breakfast_included')
                    ->label('Breakfast')
                    ->placeholder('All plans')
                    ->trueLabel('With breakfast')
                    ->falseLabel('Without breakfast'),
                    
                Tables\Filters\SelectFilter::make('country_restriction_type')
                    ->label('Country Restrictions')
                    ->options([
                        'none' => 'No Restrictions',
                        'include_only' => 'Include Only',
                        'exclude_only' => 'Exclude Only',
                    ]),
                    
                Tables\Filters\SelectFilter::make('applicable_countries')
                    ->label('Available In Country')
                    ->options(RatePlan::getCountriesList())
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) {
                            return $query;
                        }
                        
                        return $query->where(function ($q) use ($data) {
                            $q->where('country_restriction_type', 'none')
                              ->orWhere(function ($qq) use ($data) {
                                  $qq->where('country_restriction_type', 'include_only')
                                     ->whereJsonContains('applicable_countries', $data['value']);
                              })
                              ->orWhere(function ($qq) use ($data) {
                                  $qq->where('country_restriction_type', 'exclude_only')
                                     ->whereJsonDoesntContain('excluded_countries', $data['value']);
                              });
                        });
                    }),
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
            'index' => Pages\ListRatePlans::route('/'),
            'create' => Pages\CreateRatePlan::route('/create'),
            'view' => Pages\ViewRatePlan::route('/{record}'),
            'edit' => Pages\EditRatePlan::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Rate Plan Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('roomType.resort.name')
                            ->label('Resort')
                            ->weight(FontWeight::Bold),
                        
                        Infolists\Components\TextEntry::make('roomType.name')
                            ->label('Room Type'),
                            
                        Infolists\Components\TextEntry::make('name')
                            ->label('Rate Plan Name'),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Plan Features')
                    ->schema([
                        Infolists\Components\IconEntry::make('refundable')
                            ->label('Refundable')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                            
                        Infolists\Components\IconEntry::make('breakfast_included')
                            ->label('Breakfast Included')
                            ->boolean()
                            ->trueIcon('heroicon-o-cake')
                            ->falseIcon('heroicon-o-x-mark')
                            ->trueColor('success')
                            ->falseColor('gray'),
                            
                        Infolists\Components\IconEntry::make('deposit_required')
                            ->label('Deposit Required')
                            ->boolean()
                            ->trueIcon('heroicon-o-credit-card')
                            ->falseIcon('heroicon-o-x-mark')
                            ->trueColor('warning')
                            ->falseColor('gray'),
                            
                        Infolists\Components\TextEntry::make('deposit_percentage')
                            ->label('Deposit Percentage')
                            ->suffix('%')
                            ->visible(fn ($record) => $record->deposit_required),
                    ])
                    ->columns(4),
                    
                Infolists\Components\Section::make('Cancellation Policy')
                    ->schema([
                        Infolists\Components\TextEntry::make('cancellation_policy')
                            ->label('Cancellation Policy')
                            ->html()
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Country Restrictions')
                    ->schema([
                        Infolists\Components\TextEntry::make('country_restriction_type')
                            ->label('Restriction Type')
                            ->formatStateUsing(fn ($state) => match($state) {
                                'none' => 'No Restrictions',
                                'include_only' => 'Include Only Selected Countries',
                                'exclude_only' => 'Exclude Selected Countries',
                                default => 'No Restrictions',
                            })
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'none' => 'gray',
                                'include_only' => 'success',
                                'exclude_only' => 'warning',
                                default => 'gray',
                            }),
                            
                        Infolists\Components\TextEntry::make('applicable_countries')
                            ->label('Available In Countries')
                            ->visible(fn ($record) => $record->country_restriction_type === 'include_only')
                            ->bulleted()
                            ->formatStateUsing(function ($state) {
                                if (!$state || count($state) === 0) {
                                    return ['No countries selected'];
                                }
                                
                                return collect($state)
                                    ->map(fn ($code) => RatePlan::getCountriesList()[$code] ?? $code)
                                    ->toArray();
                            }),
                            
                        Infolists\Components\TextEntry::make('excluded_countries')
                            ->label('Excluded Countries')
                            ->visible(fn ($record) => $record->country_restriction_type === 'exclude_only')
                            ->bulleted()
                            ->formatStateUsing(function ($state) {
                                if (!$state || count($state) === 0) {
                                    return ['No countries selected'];
                                }
                                
                                return collect($state)
                                    ->map(fn ($code) => RatePlan::getCountriesList()[$code] ?? $code)
                                    ->toArray();
                            }),
                    ])
                    ->columns(1),
                    
                Infolists\Components\Section::make('Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('active')
                            ->label('Active')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                            
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->since(),
                            
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->since(),
                    ])
                    ->columns(3),
            ]);
    }
}
