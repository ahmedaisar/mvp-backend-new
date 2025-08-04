<?php

namespace App\Filament\Pages;

use App\Models\RatePlan;
use App\Models\Inventory;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class InventoryCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    
    protected static ?string $navigationGroup = 'Pricing & Revenue';
    
    protected static ?int $navigationSort = 4;
    
    protected static string $view = 'filament.pages.inventory-calendar';
    
    protected static ?string $title = 'Inventory Calendar';
    
    public ?array $data = [];
    public $selectedRatePlan = null;
    public $currentMonth;
    public $currentYear;
    
    public function mount(): void
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->form->fill();
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rate Plan Selection')
                    ->schema([
                        Forms\Components\Select::make('selectedRatePlan')
                            ->label('Select Rate Plan')
                            ->options(RatePlan::with('roomType.resort')
                                ->get()
                                ->mapWithKeys(function ($ratePlan) {
                                    return [
                                        $ratePlan->id => $ratePlan->roomType->resort->name . ' - ' . 
                                                       $ratePlan->roomType->name . ' - ' . 
                                                       $ratePlan->name
                                    ];
                                }))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->loadInventory();
                            }),
                            
                        Forms\Components\Select::make('currentMonth')
                            ->label('Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            ])
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->loadInventory();
                            }),
                            
                        Forms\Components\Select::make('currentYear')
                            ->label('Year')
                            ->options(collect(range(now()->year, now()->year + 2))
                                ->mapWithKeys(fn($year) => [$year => $year]))
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->loadInventory();
                            }),
                    ])
                    ->columns(3)
            ])
            ->statePath('data');
    }
    
    public function loadInventory(): void
    {
        if (!$this->selectedRatePlan) {
            return;
        }
        
        $this->dispatch('inventory-updated');
    }
    
    public function getInventoryForMonth(): Collection
    {
        if (!$this->selectedRatePlan) {
            return collect();
        }
        
        $startDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        return Inventory::where('rate_plan_id', $this->selectedRatePlan)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(function ($item) {
                return $item->date->format('Y-m-d');
            });
    }
    
    public function getCalendarData(): array
    {
        $startDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $inventory = $this->getInventoryForMonth();
        
        $calendar = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $inventoryItem = $inventory->get($dateKey);
            
            $calendar[] = [
                'date' => $current->copy(),
                'day' => $current->day,
                'available_rooms' => $inventoryItem?->available_rooms ?? 0,
                'blocked' => $inventoryItem?->blocked ?? false,
                'has_inventory' => $inventoryItem !== null,
                'inventory_id' => $inventoryItem?->id,
                'is_past' => $current->isPast(),
                'is_weekend' => $current->isWeekend(),
            ];
            
            $current->addDay();
        }
        
        return $calendar;
    }
    
    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadInventory();
    }
    
    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadInventory();
    }
    
    public function updateInventory($date, $availableRooms, $blocked = false): void
    {
        if (!$this->selectedRatePlan || !$date) {
            return;
        }
        
        $carbonDate = Carbon::parse($date);
        
        Inventory::updateOrCreate(
            [
                'rate_plan_id' => $this->selectedRatePlan,
                'date' => $carbonDate,
            ],
            [
                'available_rooms' => max(0, (int) $availableRooms),
                'blocked' => (bool) $blocked,
            ]
        );
        
        $this->loadInventory();
        
        $this->dispatch('inventory-updated', [
            'message' => 'Inventory updated for ' . $carbonDate->format('M j, Y')
        ]);
    }
    
    public function bulkUpdate($startDate, $endDate, $availableRooms, $blocked = false): void
    {
        if (!$this->selectedRatePlan || !$startDate || !$endDate) {
            return;
        }
        
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $current = $start->copy();
        $count = 0;
        
        while ($current <= $end) {
            Inventory::updateOrCreate(
                [
                    'rate_plan_id' => $this->selectedRatePlan,
                    'date' => $current->copy(),
                ],
                [
                    'available_rooms' => max(0, (int) $availableRooms),
                    'blocked' => (bool) $blocked,
                ]
            );
            
            $current->addDay();
            $count++;
        }
        
        $this->loadInventory();
        
        $this->dispatch('inventory-updated', [
            'message' => "Inventory updated for {$count} days from " . $start->format('M j') . ' to ' . $end->format('M j, Y')
        ]);
    }
    
    public function blockDateRange($startDate, $endDate): void
    {
        $this->bulkUpdate($startDate, $endDate, 0, true);
    }
    
    public function unblockDateRange($startDate, $endDate): void
    {
        $this->bulkUpdate($startDate, $endDate, 10, false); // Default to 10 rooms
    }
}
