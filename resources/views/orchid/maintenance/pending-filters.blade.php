@include('orchid.partials.product-ui-styles')

@php
    $q = fn (array $over) => request()->fullUrlWithQuery($over + ['page' => null]);
@endphp

<div class="bg-white rounded shadow-sm p-3 mb-3">
    {{-- Producto: same axis as the dashboard (advertising_spaces.category) --}}
    <div class="product-filter-bar mb-2">
        <span class="pf-label">Producto</span>
        <a href="{{ $q(['producto' => null]) }}" class="pf-chip {{ $filters['producto'] ? '' : 'active' }}">Todos</a>
        @foreach($categories as $cat)
            <a href="{{ $q(['producto' => $cat]) }}" class="pf-chip {{ $filters['producto'] === $cat ? 'active' : '' }}">
                {{ \Illuminate\Support\Str::title(mb_strtolower($cat)) }}
            </a>
        @endforeach
    </div>

    <form method="get" class="product-filter-bar">
        @foreach(['externalCode', 'producto'] as $keep)
            @if(request($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
        @endforeach

        <label class="pf-select">
            <span>Ciudad</span>
            <select name="city" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach($cities as $city)
                    <option value="{{ $city }}" @selected($filters['city'] === $city)>{{ $city }}</option>
                @endforeach
            </select>
        </label>

        <label class="pf-select">
            <span>Desde</span>
            <input type="date" name="from" value="{{ $filters['date_from'] }}" onchange="this.form.submit()">
        </label>
        <label class="pf-select">
            <span>Hasta</span>
            <input type="date" name="to" value="{{ $filters['date_to'] }}" onchange="this.form.submit()">
        </label>

        @if($filters['city'] || $filters['date_from'] || $filters['date_to'] || $filters['producto'])
            <a href="{{ route('platform.maintenances.pending') }}" class="pf-chip">Limpiar</a>
        @endif
    </form>
</div>
