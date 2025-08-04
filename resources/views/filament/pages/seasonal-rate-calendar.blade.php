<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
        
        @if($this->selectedRatePlan)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ \Carbon\Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->format('F Y') }} Calendar
                    </h2>
                    <div class="flex space-x-2">
                        <button wire:click="previousMonth" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-md text-sm font-medium">
                            ← Previous
                        </button>
                        <button wire:click="nextMonth" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-md text-sm font-medium">
                            Next →
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-7 gap-1">
                    <!-- Day headers -->
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                        <div class="p-2 text-center text-sm font-medium text-gray-500 bg-gray-50">
                            {{ $day }}
                        </div>
                    @endforeach
                    
                    <!-- Calendar days -->
                    @php
                        $calendarData = $this->getCalendarData();
                        $startDate = \Carbon\Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
                        $startWeekday = $startDate->dayOfWeek;
                    @endphp
                    
                    <!-- Empty cells for days before month starts -->
                    @for($i = 0; $i < $startWeekday; $i++)
                        <div class="aspect-square p-2 bg-gray-50"></div>
                    @endfor
                    
                    <!-- Calendar days with rates -->
                    @foreach($calendarData as $day)
                        <div class="aspect-square border border-gray-200 p-1 relative group hover:bg-gray-50 cursor-pointer"
                             x-data="{ 
                                editing: false, 
                                price: '{{ $day['price'] }}',
                                originalPrice: '{{ $day['price'] }}'
                             }"
                             @click="editing = true">
                            
                            <!-- Day number -->
                            <div class="text-sm font-medium {{ $day['has_rate'] ? 'text-blue-600' : 'text-gray-900' }}">
                                {{ $day['day'] }}
                            </div>
                            
                            <!-- Rate display/edit -->
                            <div class="mt-1">
                                <div x-show="!editing" class="text-xs">
                                    @if($day['has_rate'])
                                        <div class="bg-blue-100 text-blue-800 px-1 py-0.5 rounded text-center">
                                            ${{ number_format($day['price'], 0) }}
                                        </div>
                                        @if($day['min_stay'] > 1)
                                            <div class="text-gray-500 text-center mt-1">
                                                {{ $day['min_stay'] }}n min
                                            </div>
                                        @endif
                                    @else
                                        <div class="bg-gray-100 text-gray-500 px-1 py-0.5 rounded text-center">
                                            No rate
                                        </div>
                                    @endif
                                </div>
                                
                                <div x-show="editing" class="space-y-1">
                                    <input type="number" 
                                           x-model="price"
                                           class="w-full text-xs border border-gray-300 rounded px-1 py-0.5"
                                           placeholder="Price"
                                           step="0.01"
                                           min="0"
                                           @keydown.enter="
                                               $wire.updateRate('{{ $day['date']->format('Y-m-d') }}', price);
                                               editing = false;
                                               originalPrice = price;
                                           "
                                           @keydown.escape="
                                               price = originalPrice;
                                               editing = false;
                                           "
                                           @blur="
                                               if (price !== originalPrice) {
                                                   $wire.updateRate('{{ $day['date']->format('Y-m-d') }}', price);
                                                   originalPrice = price;
                                               }
                                               editing = false;
                                           ">
                                    <div class="text-xs text-gray-500 text-center">
                                        Enter/Click away to save
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hover overlay -->
                            <div class="absolute inset-0 bg-blue-500 bg-opacity-10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-6 text-sm text-gray-600 space-y-2">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-blue-100 border border-blue-200 rounded"></div>
                            <span>Has seasonal rate</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-gray-100 border border-gray-200 rounded"></div>
                            <span>No rate set</span>
                        </div>
                    </div>
                    <p><strong>Instructions:</strong> Click on any day to edit the rate. Press Enter or click away to save changes.</p>
                </div>
            </div>
        @else
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <div class="text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Rate Plan Selected</h3>
                    <p class="mt-1 text-sm text-gray-500">Select a rate plan above to view and edit seasonal rates.</p>
                </div>
            </div>
        @endif
    </div>
    
    @push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('rate-updated', (event) => {
                // Show success notification
                if (window.FilamentData && window.FilamentData.notifications) {
                    window.FilamentData.notifications.push({
                        title: 'Success',
                        body: event.message || 'Rate updated successfully',
                        color: 'success',
                        duration: 3000
                    });
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
