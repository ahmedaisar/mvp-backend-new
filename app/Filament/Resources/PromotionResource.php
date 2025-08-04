<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use App\Models\Resort;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Enums\FontWeight;
use Carbon\Carbon;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing & Promotions';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Promotion Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Promotion Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Summer Special 2025'),

                                Forms\Components\TextInput::make('code')
                                    ->label('Promotion Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('SUMMER25')
                                    ->alphaDash(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Detailed description of the promotion'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Promotion Type')
                                    ->options([
                                        'percentage' => 'Percentage Discount',
                                        'fixed_amount' => 'Fixed Amount Discount',
                                        'buy_x_get_y' => 'Buy X Get Y',
                                        'free_nights' => 'Free Nights',
                                        'upgrade' => 'Room Upgrade',
                                        'package_deal' => 'Package Deal',
                                    ])
                                    ->required()
                                    ->reactive(),

                                Forms\Components\TextInput::make('discount_value')
                                    ->label('Discount Value')
                                    ->numeric()
                                    ->required()
                                    ->suffix(fn (Forms\Get $get): string => $get('type') === 'percentage' ? '%' : '$')
                                    ->visible(fn (Forms\Get $get): bool => in_array($get('type'), ['percentage', 'fixed_amount'])),

                                Forms\Components\TextInput::make('min_nights')
                                    ->label('Minimum Nights')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),
                            ]),
                    ]),

                Forms\Components\Section::make('Validity Period')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('valid_from')
                                    ->label('Valid From')
                                    ->required()
                                    ->default(now()),

                                Forms\Components\DateTimePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->required()
                                    ->after('valid_from'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('blackout_dates')
                                    ->label('Blackout Dates')
                                    ->multiple()
                                    ->displayFormat('M j, Y'),

                                Forms\Components\CheckboxList::make('valid_days')
                                    ->label('Valid Days of Week')
                                    ->options([
                                        'monday' => 'Monday',
                                        'tuesday' => 'Tuesday',
                                        'wednesday' => 'Wednesday',
                                        'thursday' => 'Thursday',
                                        'friday' => 'Friday',
                                        'saturday' => 'Saturday',
                                        'sunday' => 'Sunday',
                                    ])
                                    ->columns(4)
                                    ->default(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                            ]),
                    ]),

                Forms\Components\Section::make('Usage Limits & Conditions')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('max_uses')
                                    ->label('Maximum Total Uses')
                                    ->numeric()
                                    ->nullable()
                                    ->placeholder('Unlimited'),

                                Forms\Components\TextInput::make('max_uses_per_customer')
                                    ->label('Max Uses Per Customer')
                                    ->numeric()
                                    ->default(1),

                                Forms\Components\TextInput::make('current_uses')
                                    ->label('Current Uses')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_booking_amount')
                                    ->label('Minimum Booking Amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->nullable(),

                                Forms\Components\TextInput::make('max_discount_amount')
                                    ->label('Maximum Discount Amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->nullable()
                                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'percentage'),
                            ]),
                    ]),

                Forms\Components\Section::make('Applicable Resorts & Rooms')
                    ->schema([
                        Forms\Components\Select::make('applicable_resorts')
                            ->label('Applicable Resorts')
                            ->relationship('resorts', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('All resorts if none selected'),

                        Forms\Components\Select::make('applicable_room_types')
                            ->label('Applicable Room Types')
                            ->relationship('roomTypes', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('All room types if none selected'),

                        Forms\Components\CheckboxList::make('customer_segments')
                            ->label('Customer Segments')
                            ->options([
                                'new_customers' => 'New Customers',
                                'returning_customers' => 'Returning Customers',
                                'vip_customers' => 'VIP Customers',
                                'corporate_customers' => 'Corporate Customers',
                                'group_bookings' => 'Group Bookings',
                            ])
                            ->columns(3),
                    ]),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_public')
                                    ->label('Public Promotion')
                                    ->helperText('Publicly visible promotions appear on website')
                                    ->default(false),

                                Forms\Components\Toggle::make('combinable_with_other_promotions')
                                    ->label('Combinable')
                                    ->helperText('Can be combined with other promotions')
                                    ->default(false),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher numbers = higher priority'),

                                Forms\Components\Select::make('auto_apply')
                                    ->label('Auto Apply')
                                    ->options([
                                        'none' => 'Never',
                                        'best_discount' => 'If Best Discount',
                                        'always' => 'Always',
                                    ])
                                    ->default('none'),
                            ]),
                    ]),

                Forms\Components\Section::make('Additional Settings')
                    ->schema([
                        Forms\Components\Textarea::make('terms_conditions')
                            ->label('Terms & Conditions')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Promotion Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->badge(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'success' => 'percentage',
                        'info' => 'fixed_amount',
                        'warning' => 'buy_x_get_y',
                        'purple' => 'free_nights',
                        'gray' => 'upgrade',
                        'danger' => 'package_deal',
                    ]),

                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(fn (string $state, $record): string => 
                        $record->type === 'percentage' 
                            ? $state . '%' 
                            : '$' . number_format($state, 2)
                    )
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn ($record): string => 
                        Carbon::parse($record->valid_until)->isPast() ? 'danger' : 'success'
                    ),

                Tables\Columns\TextColumn::make('current_uses')
                    ->label('Uses')
                    ->formatStateUsing(fn (string $state, $record): string => 
                        $state . ($record->max_uses ? ' / ' . $record->max_uses : '')
                    )
                    ->alignCenter(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->multiple()
                    ->options([
                        'percentage' => 'Percentage Discount',
                        'fixed_amount' => 'Fixed Amount Discount',
                        'buy_x_get_y' => 'Buy X Get Y',
                        'free_nights' => 'Free Nights',
                        'upgrade' => 'Room Upgrade',
                        'package_deal' => 'Package Deal',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),

                Tables\Filters\Filter::make('valid_now')
                    ->label('Currently Valid')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('valid_from', '<=', now())
                              ->where('valid_until', '>=', now())
                    ),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring in 7 Days')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('valid_until', '<=', now()->addDays(7))
                              ->where('valid_until', '>=', now())
                    ),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Valid From'),
                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Valid Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['valid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('valid_from', '>=', $date),
                            )
                            ->when(
                                $data['valid_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('valid_until', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-m-square-2-stack')
                    ->color('gray')
                    ->action(function (Promotion $record): void {
                        $newPromotion = $record->replicate();
                        $newPromotion->name = $record->name . ' (Copy)';
                        $newPromotion->code = $record->code . '_COPY';
                        $newPromotion->current_uses = 0;
                        $newPromotion->is_active = false;
                        $newPromotion->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (Promotion $record) => $record->update(['is_active' => true]))),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each(fn (Promotion $record) => $record->update(['is_active' => false]))),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Promotion Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Name')
                                    ->weight(FontWeight::Bold),
                                    
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Code')
                                    ->copyable()
                                    ->badge(),

                                Infolists\Components\TextEntry::make('type')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('discount_value')
                                    ->label('Discount Value')
                                    ->formatStateUsing(fn (string $state, $record): string => 
                                        $record->type === 'percentage' 
                                            ? $state . '%' 
                                            : '$' . number_format($state, 2)
                                    ),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Validity & Usage')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('valid_from')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('valid_until')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('current_uses')
                                    ->formatStateUsing(fn (string $state, $record): string => 
                                        $state . ($record->max_uses ? ' / ' . $record->max_uses : ' (Unlimited)')
                                    ),

                                Infolists\Components\TextEntry::make('max_uses_per_customer')
                                    ->label('Max Uses Per Customer'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Conditions')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('min_booking_amount')
                                    ->money('USD')
                                    ->placeholder('No minimum'),

                                Infolists\Components\TextEntry::make('min_nights')
                                    ->suffix(' nights'),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->boolean(),

                                Infolists\Components\IconEntry::make('is_public')
                                    ->boolean(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Terms & Conditions')
                    ->schema([
                        Infolists\Components\TextEntry::make('terms_conditions')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Promotion $record): bool => !empty($record->terms_conditions))
                    ->collapsed(),
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
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'view' => Pages\ViewPromotion::route('/{record}'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)
            ->where('valid_until', '<=', now()->addDays(7))
            ->where('valid_until', '>=', now())
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}
