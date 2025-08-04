<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Key Performance Indicators</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($this->getHeaderWidgets() as $widget)
                    @livewire($widget)
                @endforeach
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach ($this->getFooterWidgets() as $widget)
                <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
                    @livewire($widget)
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
