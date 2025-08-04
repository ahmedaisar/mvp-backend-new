<?php

namespace App\Filament\Pages;

use App\Models\RatePlan;
use App\Models\SeasonalRate;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class SeasonalRateCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationGroup = 'Pricing & Revenue';
    
    protected static ?int $navigationSort = 5;
    
    protected static string $view = 'filament.pages.seasonal-rate-calendar';
    
    protected static ?string $title = 'Seasonal Rate Calendar';
    
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
                                $this->loadSeasonalRates();
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
                                $this->loadSeasonalRates();
                            }),
                            
                        Forms\Components\Select::make('currentYear')
                            ->label('Year')
                            ->options(collect(range(now()->year, now()->year + 2))
                                ->mapWithKeys(fn($year) => [$year => $year]))
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->loadSeasonalRates();
                            }),
                    ])
                    ->columns(3)
            ])
            ->statePath('data');
    }
    
    public function loadSeasonalRates(): void
    {
        if (!$this->selectedRatePlan) {
            return;
        }
        
        // This will trigger a re-render of the calendar component
        $this->dispatch('seasonal-rates-updated');
    }
    
    public function getSeasonalRatesForMonth(): Collection
    {
        if (!$this->selectedRatePlan) {
            return collect();
        }
        
        $startDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        return SeasonalRate::where('rate_plan_id', $this->selectedRatePlan)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->orderBy('start_date')
            ->get();
    }
    
    public function getCalendarData(): array
    {
        $startDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $seasonalRates = $this->getSeasonalRatesForMonth();
        
        $calendar = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $rate = $seasonalRates->first(function ($seasonalRate) use ($current) {
                return $current->between($seasonalRate->start_date, $seasonalRate->end_date);
            });
            
            $calendar[] = [
                'date' => $current->copy(),
                'day' => $current->day,
                'price' => $rate?->nightly_price ?? 0,
                'min_stay' => $rate?->min_stay ?? 1,
                'max_stay' => $rate?->max_stay ?? null,
                'has_rate' => $rate !== null,
                'rate_id' => $rate?->id,
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
        $this->loadSeasonalRates();
    }
    
    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadSeasonalRates();
    }
    
    public function updateRate($date, $price): void
    {
        if (!$this->selectedRatePlan || !$date || !$price) {
            return;
        }
        
        $carbonDate = Carbon::parse($date);
        
        // Find existing rate for this date
        $existingRate = SeasonalRate::where('rate_plan_id', $this->selectedRatePlan)
            ->where('start_date', '<=', $carbonDate)
            ->where('end_date', '>=', $carbonDate)
            ->first();
            
        if ($existingRate) {
            // Update existing rate
            $existingRate->update(['nightly_price' => $price]);
        } else {
            // Create new rate for single day
            SeasonalRate::create([
                'rate_plan_id' => $this->selectedRatePlan,
                'start_date' => $carbonDate,
                'end_date' => $carbonDate,
                'nightly_price' => $price,
                'min_stay' => 1,
                'max_stay' => null,
            ]);
        }
        
        $this->loadSeasonalRates();
        
        $this->dispatch('rate-updated', [
            'message' => 'Rate updated successfully for ' . $carbonDate->format('M j, Y')
        ]);
    }
}
