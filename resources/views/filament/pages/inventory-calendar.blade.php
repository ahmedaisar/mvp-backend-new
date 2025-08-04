<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
        
        @if($this->selectedRatePlan)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ \Carbon\Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->format('F Y') }} Inventory
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
                
                <!-- Bulk Actions -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Bulk Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-700">Date Range</label>
                            <div class="flex space-x-2">
                                <input type="date" id="bulk-start-date" class="text-xs border border-gray-300 rounded px-2 py-1">
                                <input type="date" id="bulk-end-date" class="text-xs border border-gray-300 rounded px-2 py-1">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-700">Available Rooms</label>
                            <input type="number" id="bulk-rooms" min="0" max="100" class="text-xs border border-gray-300 rounded px-2 py-1 w-full">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-700">Actions</label>
                            <div class="flex space-x-2">
                                <button onclick="bulkUpdate()" 
                                        class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                    Update
                                </button>
                                <button onclick="bulkBlock()" 
                                        class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                    Block
                                </button>
                                <button onclick="bulkUnblock()" 
                                        class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                    Unblock
                                </button>
                            </div>
                        </div>
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
                    
                    <!-- Calendar days with inventory -->
                    @foreach($calendarData as $day)
                        <div class="aspect-square border border-gray-200 p-1 relative group hover:bg-gray-50 cursor-pointer
                                    {{ $day['blocked'] ? 'bg-red-50' : '' }}
                                    {{ $day['is_weekend'] ? 'bg-blue-50' : '' }}
                                    {{ $day['is_past'] ? 'opacity-60' : '' }}"
                             x-data="{ 
                                editing: false, 
                                rooms: '{{ $day['available_rooms'] }}',
                                originalRooms: '{{ $day['available_rooms'] }}',
                                blocked: {{ $day['blocked'] ? 'true' : 'false' }}
                             }"
                             @click="if (!{{ $day['is_past'] ? 'true' : 'false' }}) editing = true">
                            
                            <!-- Day number -->
                            <div class="text-sm font-medium {{ $day['blocked'] ? 'text-red-600' : ($day['available_rooms'] > 0 ? 'text-green-600' : 'text-gray-500') }}">
                                {{ $day['day'] }}
                            </div>
                            
                            <!-- Inventory display/edit -->
                            <div class="mt-1">
                                <div x-show="!editing" class="text-xs space-y-1">
                                    @if($day['blocked'])
                                        <div class="bg-red-100 text-red-800 px-1 py-0.5 rounded text-center font-medium">
                                            BLOCKED
                                        </div>
                                    @elseif($day['available_rooms'] > 0)
                                        <div class="bg-green-100 text-green-800 px-1 py-0.5 rounded text-center">
                                            {{ $day['available_rooms'] }} rooms
                                        </div>
                                    @else
                                        <div class="bg-gray-100 text-gray-500 px-1 py-0.5 rounded text-center">
                                            Sold out
                                        </div>
                                    @endif
                                </div>
                                
                                <div x-show="editing" class="space-y-1">
                                    <input type="number" 
                                           x-model="rooms"
                                           class="w-full text-xs border border-gray-300 rounded px-1 py-0.5"
                                           placeholder="Rooms"
                                           min="0"
                                           max="100"
                                           @keydown.enter="
                                               $wire.updateInventory('{{ $day['date']->format('Y-m-d') }}', rooms, blocked);
                                               editing = false;
                                               originalRooms = rooms;
                                           "
                                           @keydown.escape="
                                               rooms = originalRooms;
                                               editing = false;
                                           "
                                           @blur="
                                               if (rooms !== originalRooms) {
                                                   $wire.updateInventory('{{ $day['date']->format('Y-m-d') }}', rooms, blocked);
                                                   originalRooms = rooms;
                                               }
                                               editing = false;
                                           ">
                                    <label class="flex items-center space-x-1">
                                        <input type="checkbox" x-model="blocked" class="text-xs">
                                        <span class="text-xs text-gray-600">Block</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Status indicators -->
                            <div class="absolute top-1 right-1 flex space-x-1">
                                @if($day['is_weekend'])
                                    <div class="w-2 h-2 bg-blue-400 rounded-full" title="Weekend"></div>
                                @endif
                                @if($day['blocked'])
                                    <div class="w-2 h-2 bg-red-500 rounded-full" title="Blocked"></div>
                                @endif
                            </div>
                            
                            <!-- Hover overlay -->
                            @if(!$day['is_past'])
                                <div class="absolute inset-0 bg-blue-500 bg-opacity-10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-6 text-sm text-gray-600 space-y-2">
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-green-100 border border-green-200 rounded"></div>
                            <span>Available rooms</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-red-100 border border-red-200 rounded"></div>
                            <span>Blocked</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-gray-100 border border-gray-200 rounded"></div>
                            <span>Sold out</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-blue-50 border border-blue-200 rounded"></div>
                            <span>Weekend</span>
                        </div>
                    </div>
                    <p><strong>Instructions:</strong> Click on any future date to edit inventory. Use bulk actions above for date ranges.</p>
                </div>
            </div>
        @else
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <div class="text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Rate Plan Selected</h3>
                    <p class="mt-1 text-sm text-gray-500">Select a rate plan above to view and manage inventory.</p>
                </div>
            </div>
        @endif
    </div>
    
    @push('scripts')
    <script>
        function bulkUpdate() {
            const startDate = document.getElementById('bulk-start-date').value;
            const endDate = document.getElementById('bulk-end-date').value;
            const rooms = document.getElementById('bulk-rooms').value;
            
            if (!startDate || !endDate || rooms === '') {
                alert('Please fill in all fields');
                return;
            }
            
            window.Livewire.find('{{ $this->getId() }}').bulkUpdate(startDate, endDate, rooms, false);
        }
        
        function bulkBlock() {
            const startDate = document.getElementById('bulk-start-date').value;
            const endDate = document.getElementById('bulk-end-date').value;
            
            if (!startDate || !endDate) {
                alert('Please select date range');
                return;
            }
            
            window.Livewire.find('{{ $this->getId() }}').blockDateRange(startDate, endDate);
        }
        
        function bulkUnblock() {
            const startDate = document.getElementById('bulk-start-date').value;
            const endDate = document.getElementById('bulk-end-date').value;
            
            if (!startDate || !endDate) {
                alert('Please select date range');
                return;
            }
            
            window.Livewire.find('{{ $this->getId() }}').unblockDateRange(startDate, endDate);
        }
        
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('inventory-updated', (event) => {
                // Show success notification
                if (window.FilamentData && window.FilamentData.notifications) {
                    window.FilamentData.notifications.push({
                        title: 'Success',
                        body: event.message || 'Inventory updated successfully',
                        color: 'success',
                        duration: 3000
                    });
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
