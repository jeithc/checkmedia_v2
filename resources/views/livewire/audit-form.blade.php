<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Nueva Auditoría</h2>
        <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold uppercase tracking-wide">
            v2.0 Beta
        </span>
    </div>

    <!-- 1. Search Section (Card) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Código del Espacio</label>
            <div class="flex items-center gap-3">
                <div class="relative flex-grow">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" wire:model="external_code" wire:keydown.enter="searchSpace"
                        class="pl-10 block w-full h-12 rounded-xl border-gray-300 bg-gray-50/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 sm:text-sm shadow-sm"
                        placeholder="Ingrese el código (Ej: 25889)">
                </div>
                <button wire:click="searchSpace" 
                    class="h-12 bg-gray-900 text-white px-8 py-2 rounded-xl hover:bg-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all duration-200 font-bold shadow-lg shadow-gray-900/20 active:scale-95">
                    Buscar
                </button>
            </div>
            @error('external_code')
                <p class="mt-2 text-red-500 text-sm flex items-center">
                    <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    {{ $message }}
                </p>
            @enderror
        </div>
    </div>

    @if($space)
        <form wire:submit.prevent="save" class="space-y-6">

            <!-- 2. Info Widget (Grid) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $space->external_code }}</h3>
                        <p class="text-sm text-gray-500">{{ $space->type }}</p>
                    </div>
                    <div class="bg-white px-3 py-1 rounded border border-gray-200 text-xs font-mono text-gray-500">
                        ID: {{ $space->id }}
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8">
                    <!-- Location Code -->
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ubicación</label>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $space->address }}</p>
                        <p class="text-xs text-gray-500">{{ $space->city }} - {{ $space->location_name }}</p>
                    </div>

                    <!-- Provider -->
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Proveedor</label>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $space->provider }}</p>
                    </div>

                    <!-- Client Alert -->
                    <div class="md:col-span-2 mt-2">
                        @if($booking)
                            <div class="bg-green-50 border border-green-100 rounded-lg p-4 flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-bold text-green-800">
                                        Cliente Vigente: {{ $booking->client_name }}
                                    </h3>
                                    <div class="mt-1 text-sm text-green-700">
                                        <p>{{ $booking->product_name }}</p>
                                        <p class="text-xs opacity-75 mt-0.5">Contrato: {{ $booking->contract_code }} (Semana
                                            {{ $booking->week }})</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-4 flex">
                                <svg class="h-5 w-5 text-yellow-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm font-medium text-yellow-800">Sin pauta comercial asignada para esta
                                    semana.</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 3. Criteria List -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Estado del Elemento</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($criteria as $criterion)
                            <div class="px-6 py-4 hover:bg-gray-50 transition-colors">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <label class="text-sm font-medium text-gray-900 w-1/3">{{ $criterion->name }}</label>

                                    <div class="flex items-center space-x-2">
                                        <!-- Good Button -->
                                        <button type="button" wire:click="$set('values.{{ $criterion->id }}.value', 'good')" class="relative inline-flex items-center px-4 py-2 rounded-full border text-sm font-medium focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all duration-200
                                                    {{ $values[$criterion->id]['value'] === 'good'
                        ? 'bg-green-100 border-green-200 text-green-800 ring-2 ring-green-500 ring-offset-2'
                        : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                                            <svg class="mr-1.5 h-4 w-4 {{ $values[$criterion->id]['value'] === 'good' ? 'text-green-600' : 'text-gray-400' }}"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Bueno
                                        </button>

                                        <!-- Bad Button -->
                                        <button type="button" wire:click="$set('values.{{ $criterion->id }}.value', 'bad')" class="relative inline-flex items-center px-4 py-2 rounded-full border text-sm font-medium focus:z-10 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all duration-200
                                                    {{ $values[$criterion->id]['value'] === 'bad'
                        ? 'bg-red-100 border-red-200 text-red-800 ring-2 ring-red-500 ring-offset-2'
                        : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                                            <svg class="mr-1.5 h-4 w-4 {{ $values[$criterion->id]['value'] === 'bad' ? 'text-red-600' : 'text-gray-400' }}"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Malo / Daño
                                        </button>
                                    </div>
                                </div>

                                <!-- Comment Slide Down -->
                                @if(isset($values[$criterion->id]['value']) && $values[$criterion->id]['value'] === 'bad')
                                    <div class="mt-3 pl-0 sm:pl-[33%] transition-all duration-300">
                                        <textarea wire:model="values.{{ $criterion->id }}.comment"
                                            class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                            rows="2" placeholder="Detalle el daño encontrado..."></textarea>
                                    </div>
                                @endif
                            </div>
                    @endforeach
                </div>
            </div>

            <!-- 4. Client Side Compression & Preview -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Evidencias</h3>
                    <span class="text-xs text-gray-500">{{ count($photos) }} fotos seleccionadas</span>
                </div>

                <div class="p-6" x-data="{ isUploading: false, progress: 0 }"
                    x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false"
                    x-on:livewire-upload-error="isUploading = false"
                    x-on:livewire-upload-progress="progress = $event.detail.progress">

                    <!-- Compression Logic -->
                    <input type="file" id="fileInput" multiple accept="image/*" class="hidden" x-on:change="
                                let files = $event.target.files;
                                if(files.length > 0){
                                    isUploading = true;
                                    progress = 10;
                                    let completed = 0;
                                    let total = files.length;

                                    for (let i = 0; i < total; i++) {
                                        new Compressor(files[i], {
                                            quality: 0.6,
                                            maxWidth: 1600,
                                            maxHeight: 1600,
                                            success(result) {
                                                let file = new File([result], result.name, { type: result.type });
                                                // Upload directly to Livewire
                                                @this.upload('photos', file, 
                                                    (uploadedFilename) => { completed++; }, 
                                                    () => { console.error('Upload failed'); completed++; }, 
                                                    (event) => { progress = event.detail.progress }
                                                );
                                            },
                                            error(err) {
                                                console.log(err.message);
                                                completed++;
                                            },
                                        });
                                    }
                                }
                            ">

                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-4">
                        <!-- Add Button -->
                        <div onclick="document.getElementById('fileInput').click()"
                            class="aspect-square rounded-lg border-2 border-dashed border-gray-300 hover:border-blue-500 hover:bg-blue-50 cursor-pointer flex flex-col items-center justify-center transition-colors group">
                            <svg class="h-8 w-8 text-gray-400 group-hover:text-blue-500" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="mt-2 text-xs font-medium text-gray-500 group-hover:text-blue-600">Agregar</span>
                        </div>

                        <!-- Previews -->
                        @foreach ($photos as $index => $photo)
                            <div
                                class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 shadow-sm border border-gray-200">
                                <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">

                                <!-- Cancel/Remove Overlay -->
                                <button type="button" wire:click="removePhoto({{ $index }})"
                                    class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <div
                                        class="bg-red-600 text-white text-xs px-2 py-1 rounded-md flex items-center shadow-lg transform scale-95 group-hover:scale-105 transition-transform">
                                        <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Cancelar
                                    </div>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <!-- Progress Bar -->
                    <div x-show="isUploading" class="w-full bg-gray-200 rounded-full h-2.5 mt-2 overflow-hidden">
                        <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                            x-bind:style="'width: ' + progress + '%'"></div>
                    </div>
                </div>
            </div>

            <!-- 5. Observations (Card) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observación General</label>
                <textarea wire:model="observation"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    rows="3" placeholder="Comentarios adicionales sobre el espacio o la visita..."></textarea>
            </div>

            <!-- Submit -->
            <div class="flex justify-end pt-4 pb-12">
                <button type="submit" wire:loading.attr="disabled"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all shadow-green-600/30">
                    <span wire:loading.remove>Guardar Auditoría</span>
                    <span wire:loading class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Guardando...
                    </span>
                </button>
            </div>

        </form>
    @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Busque un espacio</h3>
            <p class="mt-1 text-sm text-gray-500">Ingrese el código del elemento publicitario para comenzar.</p>
        </div>
    @endif

    <!-- Notifications -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
            class="fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-500 ease-in-out z-50">
            <svg class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Compressor.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/compressorjs/1.2.1/compressor.min.js"></script>
</div>