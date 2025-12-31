<div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-xl font-bold mb-4">Nueva Auditoría</h2>

    <!-- Space Search -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700">Código del Espacio</label>
        <div class="flex mt-1">
            <input type="text" wire:model="external_code" class="form-input rounded-l-md w-full border-gray-300"
                placeholder="Ej: 12345">
            <button wire:click="searchSpace" class="bg-blue-600 text-white px-4 py-2 rounded-r-md hover:bg-blue-700">
                Buscar
            </button>
        </div>
        @error('external_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    @if($space)
        <!-- Space Details -->
        <div class="bg-gray-50 p-4 rounded-md mb-6 border border-gray-200">
            <h3 class="font-semibold text-lg text-gray-800">{{ $space->external_code }}</h3>
            <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                <div><span class="font-medium">Ciudad:</span> {{ $space->city }}</div>
                <div><span class="font-medium">Dirección:</span> {{ $space->address }}</div>
                <div><span class="font-medium">Tipo:</span> {{ $space->type }}</div>
                <div><span class="font-medium">Proveedor:</span> {{ $space->provider }}</div>
            </div>

            <!-- Booking Info -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                @if($booking)
                    <div class="flex items-center text-green-700 bg-green-50 p-2 rounded">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <div class="font-bold">Cliente Vigente: {{ $booking->client_name }}</div>
                            <div class="text-xs">{{ $booking->product_name }} ({{ $booking->contract_code }})</div>
                        </div>
                    </div>
                @else
                    <div class="text-yellow-600 bg-yellow-50 p-2 rounded flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                        Sin pauta comercial para esta semana
                    </div>
                @endif
            </div>
        </div>

        <!-- Audit Form -->
        <form wire:submit.prevent="save">

            <div class="space-y-6">
                <!-- Dynamic Criteria -->
                @foreach($criteria as $criterion)
                    <div class="border-b border-gray-100 pb-4">
                        <label class="block text-md font-medium text-gray-800 mb-2">{{ $criterion->name }}</label>

                        <div class="flex space-x-4 mb-2">
                            <!-- Simple Good/Bad Toggle -->
                            <label class="inline-flex items-center">
                                <input type="radio" wire:model="values.{{ $criterion->id }}.value" value="good"
                                    class="form-radio text-green-600">
                                <span class="ml-2">Buen Estado</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" wire:model="values.{{ $criterion->id }}.value" value="bad"
                                    class="form-radio text-red-600">
                                <span class="ml-2">Novedad / Daño</span>
                            </label>
                        </div>

                        <!-- Comment if Bad -->
                        @if(isset($values[$criterion->id]['value']) && $values[$criterion->id]['value'] === 'bad')
                            <textarea wire:model="values.{{ $criterion->id }}.comment"
                                class="form-textarea mt-1 block w-full rounded-md border-gray-300" rows="2"
                                placeholder="Describa el daño..."></textarea>
                        @endif
                    </div>
                @endforeach

                <!-- Photos -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Evidencias Fotográficas</label>
                    <input type="file" wire:model="photos" multiple class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-full file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100
                        " />
                    @error('photos.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                    <!-- Previews -->
                    @if ($photos)
                        <div class="flex gap-2 mt-4 overflow-x-auto">
                            @foreach ($photos as $photo)
                                <img src="{{ $photo->temporaryUrl() }}" class="h-20 w-20 object-cover rounded">
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- General Observation -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Observación General</label>
                    <textarea wire:model="observation"
                        class="form-textarea mt-1 block w-full rounded-md border-gray-300 shadow-sm" rows="3"></textarea>
                </div>

                <!-- Actions -->
                <div class="flex justify-end pt-4">
                    <button type="submit"
                        class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        Guardar Auditoría
                    </button>
                </div>
            </div>
        </form>
    @endif

    @if (session()->has('message'))
        <div class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('message') }}
        </div>
    @endif
</div>