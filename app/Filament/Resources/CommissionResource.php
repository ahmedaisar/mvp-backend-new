<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionResource\Pages;
use App\Filament\Resources\CommissionResource\RelationManagers;
use App\Models\Commission;
use App\Models\Resort;
use App\Models\RoomType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Partners & Commissions';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Commissions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->label('Commission Name')
                                    ->placeholder('Enter commission name'),

                                Select::make('type')
                                    ->required()
                                    ->options([
                                        'travel_agency' => 'Travel Agency',
                                        'tour_operator' => 'Tour Operator',
                                        'online_booking' => 'Online Booking Platform',
                                        'corporate' => 'Corporate',
                                        'wholesale' => 'Wholesale',
                                        'affiliate' => 'Affiliate Partner',
                                    ])
                                    ->label('Partner Type'),

                                TextInput::make('agent_code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->label('Agent Code')
                                    ->placeholder('AGENT001')
                                    ->helperText('Unique identifier for this agent'),

                                Toggle::make('active')
                                    ->default(true)
                                    ->label('Active'),
                            ]),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('contact_name')
                                    ->required()
                                    ->label('Contact Name'),

                                TextInput::make('contact_email')
                                    ->required()
                                    ->email()
                                    ->label('Contact Email'),

                                TextInput::make('contact_phone')
                                    ->tel()
                                    ->label('Contact Phone'),
                            ]),
                    ]),

                Section::make('Commission Structure')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('commission_type')
                                    ->required()
                                    ->options([
                                        'percentage' => 'Percentage',
                                        'fixed_amount' => 'Fixed Amount',
                                    ])
                                    ->reactive()
                                    ->label('Commission Type'),

                                TextInput::make('commission_rate')
                                    ->numeric()
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->visible(fn (callable $get) => $get('commission_type') === 'percentage')
                                    ->label('Commission Rate')
                                    ->helperText('Enter percentage (e.g., 10 for 10%)'),

                                TextInput::make('fixed_amount')
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('€')
                                    ->visible(fn (callable $get) => $get('commission_type') === 'fixed_amount')
                                    ->label('Fixed Amount'),

                                Select::make('payment_frequency')
                                    ->required()
                                    ->options([
                                        'per_booking' => 'Per Booking',
                                        'monthly' => 'Monthly',
                                        'quarterly' => 'Quarterly',
                                        'annually' => 'Annually',
                                    ])
                                    ->default('per_booking')
                                    ->label('Payment Frequency'),
                            ]),
                    ]),

                Section::make('Applicability & Restrictions')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('applicable_resorts')
                                    ->multiple()
                                    ->options(Resort::pluck('name', 'id'))
                                    ->searchable()
                                    ->label('Applicable Resorts')
                                    ->helperText('Leave empty to apply to all resorts'),

                                Select::make('applicable_room_types')
                                    ->multiple()
                                    ->options(RoomType::pluck('name', 'id'))
                                    ->searchable()
                                    ->label('Applicable Room Types')
                                    ->helperText('Leave empty to apply to all room types'),

                                TextInput::make('minimum_booking_value')
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('€')
                                    ->default(0)
                                    ->label('Minimum Booking Value'),

                                TextInput::make('minimum_nights')
                                    ->numeric()
                                    ->integer()
                                    ->min(1)
                                    ->default(1)
                                    ->label('Minimum Nights'),
                            ]),
                    ]),

                Section::make('Validity Period')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('valid_from')
                                    ->required()
                                    ->default(now())
                                    ->label('Valid From'),

                                DatePicker::make('valid_until')
                                    ->after('valid_from')
                                    ->label('Valid Until')
                                    ->helperText('Leave empty for no expiration'),
                            ]),
                    ]),

                Section::make('Terms & Conditions')
                    ->schema([
                        TagsInput::make('terms_and_conditions')
                            ->label('Terms & Conditions')
                            ->placeholder('Add terms and conditions...')
                            ->helperText('Press Enter to add each term'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Name'),

                TextColumn::make('agent_code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->label('Agent Code'),

                BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'travel_agency',
                        'success' => 'tour_operator',
                        'warning' => 'online_booking',
                        'danger' => 'corporate',
                        'secondary' => 'wholesale',
                        'info' => 'affiliate',
                    ])
                    ->label('Type'),

                TextColumn::make('commission_type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                        default => $state,
                    })
                    ->label('Commission Type'),

                TextColumn::make('commission_rate')
                    ->formatStateUsing(function ($record) {
                        if ($record->commission_type === 'percentage') {
                            return $record->commission_rate . '%';
                        } elseif ($record->commission_type === 'fixed_amount') {
                            return '€' . number_format($record->fixed_amount, 2);
                        }
                        return '-';
                    })
                    ->label('Rate/Amount'),

                TextColumn::make('contact_name')
                    ->searchable()
                    ->label('Contact'),

                TextColumn::make('valid_from')
                    ->date()
                    ->sortable()
                    ->label('Valid From'),

                TextColumn::make('valid_until')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiration')
                    ->label('Valid Until'),

                BooleanColumn::make('active')
                    ->sortable()
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'travel_agency' => 'Travel Agency',
                        'tour_operator' => 'Tour Operator',
                        'online_booking' => 'Online Booking Platform',
                        'corporate' => 'Corporate',
                        'wholesale' => 'Wholesale',
                        'affiliate' => 'Affiliate Partner',
                    ])
                    ->label('Partner Type'),

                SelectFilter::make('commission_type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                    ])
                    ->label('Commission Type'),

                Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->label('Active Only'),

                Filter::make('expired')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('valid_until')
                              ->where('valid_until', '<', now())
                    )
                    ->label('Expired'),
            ])
            ->actions([
                Action::make('calculate')
                    ->icon('heroicon-o-calculator')
                    ->label('Test Commission')
                    ->form([
                        TextInput::make('booking_value')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€')
                            ->label('Booking Value'),
                        TextInput::make('nights')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->min(1)
                            ->default(1)
                            ->label('Number of Nights'),
                    ])
                    ->action(function (array $data, Commission $record): void {
                        $commission = $record->calculateCommission($data['booking_value'], $data['nights']);
                        \Filament\Notifications\Notification::make()
                            ->title('Commission Calculated')
                            ->body("Commission: €" . number_format($commission, 2))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCommissions::route('/'),
            'create' => Pages\CreateCommission::route('/create'),
            'edit' => Pages\EditCommission::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
