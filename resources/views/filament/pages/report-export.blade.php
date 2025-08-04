<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Export Reports</h2>
            
            <form wire:submit="exportReport" class="space-y-6">
                {{ $this->form }}
                
                <div class="flex space-x-4">
                    {{ $this->getFormActions() }}
                </div>
            </form>
            
            @if(session('report_preview'))
                <div class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Data Preview (First 10 rows)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                @if(count(session('report_preview')) > 0)
                                    <tr>
                                        @foreach(session('report_preview')[0] as $header)
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                {{ $header }}
                                            </th>
                                        @endforeach
                                    </tr>
                                @endif
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                @foreach(array_slice(session('report_preview'), 1) as $row)
                                    <tr>
                                        @foreach($row as $cell)
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                                                {{ $cell }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
        
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6 dark:bg-blue-900/20 dark:border-blue-800">
            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100 mb-3">Available Reports</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200">Booking Report</h4>
                    <p class="text-blue-700 dark:text-blue-300">Detailed booking information with guest details</p>
                </div>
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200">Revenue Report</h4>
                    <p class="text-blue-700 dark:text-blue-300">Daily revenue breakdown by resort</p>
                </div>
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200">Occupancy Report</h4>
                    <p class="text-blue-700 dark:text-blue-300">Room occupancy rates and trends</p>
                </div>
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200">Commission Report</h4>
                    <p class="text-blue-700 dark:text-blue-300">B2B agent commission calculations</p>
                </div>
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200">Promotion Report</h4>
                    <p class="text-blue-700 dark:text-blue-300">Promotional code usage and performance</p>
                </div>
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200">Analytics Report</h4>
                    <p class="text-blue-700 dark:text-blue-300">Comprehensive analytics summary</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
