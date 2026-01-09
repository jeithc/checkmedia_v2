@push('head')
    <style>
        .input-group-sm .input-group-text, .input-group-sm .form-control {
            min-height: 31px; /* Force consistent height for sm inputs */
        }
        
        /* Space Browser Redesign Styles from space-browser.css */
        .filter-group {
            min-width: 150px;
        }
        .filter-group label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .table thead th {
            font-weight: 600;
            color: #6b7280;
            border-bottom-width: 1px;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .table tbody td {
            vertical-align: middle;
            font-size: 0.9em;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        .badge {
            font-weight: 500;
            letter-spacing: 0.025em;
        }

        /* Pagination active state */
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white !important;
        }
        .page-link {
            color: #6b7280;
        }
        .page-link:hover {
            background-color: #f3f4f6;
        }
    </style>
@endpush


<div class="space-browser-container p-4 bg-white rounded shadow-sm">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            Constructor de Reportes de Auditoría
        </h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
            Seleccione las columnas que desea incluir en su reporte y genere una vista previa o descargue directamente a Excel.
        </p>
    </div>

    {{-- Error Messages --}}
    @if ($errors->any())
        <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ $errors->first() }}
                    </h3>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Grid Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- Left Panel: Column Selection --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-white">
                        Columnas Disponibles
                    </h2>
                    <p class="text-sm text-blue-100 mt-1">
                        {{ count($selectedColumns) }} columna(s) seleccionada(s)
                    </p>
                </div>
                
                <div class="p-6 space-y-6 max-h-[600px] overflow-y-auto">
                    {{-- Static Columns Group --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Datos Generales
                        </h3>
                        <div class="space-y-2">
                            @foreach($availableStaticColumns as $key => $label)
                                <label class="flex items-center p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="selectedColumns" 
                                        value="{{ $key }}"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    >
                                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Dynamic Criteria Columns Group --}}
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            Preguntas de Auditoría
                        </h3>
                        <div class="space-y-2">
                            @forelse($availableCriteria as $criterion)
                                <label class="flex items-center p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="selectedColumns" 
                                        value="criterion_{{ $criterion->id }}"
                                        class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500 dark:border-gray-600 dark:bg-gray-700"
                                    >
                                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $criterion->name }}
                                    </span>
                                </label>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                                    No hay criterios activos disponibles.
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 space-y-2">
                    <button 
                        wire:click="generatePreview"
                        class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <span class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Generar Vista Previa
                        </span>
                    </button>
                    
                    <button 
                        wire:click="downloadExcel"
                        class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                    >
                        <span class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Descargar Excel
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Right Panel: Preview Table --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-700 to-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-white">
                        Vista Previa del Reporte
                    </h2>
                    <p class="text-sm text-gray-300 mt-1">
                        Mostrando los primeros 10 registros
                    </p>
                </div>

                <div class="p-6">
                    @if($showPreview && $previewData && count($selectedColumns) > 0)
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        @foreach($selectedColumns as $column)
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">
                                                {{ $this->getColumnHeading($column) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($previewData as $audit)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            @foreach($selectedColumns as $column)
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300 whitespace-nowrap">
                                                    {{ $this->getCellValue($audit, $column) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($selectedColumns) }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                                No hay auditorías disponibles para mostrar.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($previewData->count() > 0)
                            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <p class="text-sm text-blue-800 dark:text-blue-200">
                                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    <strong>Nota:</strong> El archivo Excel contendrá todos los registros disponibles, no solo la vista previa.
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-16">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                                Aún no hay vista previa
                            </h3>
                            <p class="mt-2 text-gray-500 dark:text-gray-400">
                                Seleccione las columnas deseadas y haga clic en "Generar Vista Previa".
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
