@include('orchid.partials.product-ui-styles')

@php
    $q = fn (array $over) => request()->fullUrlWithQuery($over + ['page' => null]);
    $hasFilters = $filters['city'] || $filters['date_from'] || $filters['date_to'] || $filters['producto'];
@endphp

{{--
    No <form> here on purpose: Orchid wraps every Layout::view inside its global
    form#post-form (class h-100). A nested <form> is dropped by the browser and
    its controls fall into post-form, which then stretches to full height and
    pushes the table to the bottom of the page. Controls navigate by query
    string instead, like the chips do.
--}}
<div class="bg-white rounded shadow-sm p-3 mb-3" data-pending-filters>
    <div class="product-filter-bar mb-2">
        <span class="pf-label">Producto</span>
        <a href="{{ $q(['producto' => null]) }}" class="pf-chip {{ $filters['producto'] ? '' : 'active' }}">Todos</a>
        @foreach($categories as $cat)
            <a href="{{ $q(['producto' => $cat]) }}" class="pf-chip {{ $filters['producto'] === $cat ? 'active' : '' }}">
                {{ \Illuminate\Support\Str::title(mb_strtolower($cat)) }}
            </a>
        @endforeach
    </div>

    <div class="product-filter-bar">
        <label class="pf-select">
            <span>Ciudad</span>
            <select data-filter="city">
                <option value="">Todas</option>
                @foreach($cities as $city)
                    <option value="{{ $city }}" @selected($filters['city'] === $city)>{{ $city }}</option>
                @endforeach
            </select>
        </label>

        <label class="pf-select">
            <span>Desde</span>
            <input type="date" data-filter="from" value="{{ $filters['date_from'] }}">
        </label>
        <label class="pf-select">
            <span>Hasta</span>
            <input type="date" data-filter="to" value="{{ $filters['date_to'] }}">
        </label>

        @if($hasFilters)
            <a href="{{ route('platform.maintenances.pending') }}" class="pf-chip">Limpiar</a>
        @endif
    </div>
</div>

@push('scripts')
<script>
    // Each control rewrites its own query-string key and reloads; page resets.
    document.querySelectorAll('[data-pending-filters] [data-filter]').forEach(function (el) {
        el.addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (el.value) { url.searchParams.set(el.dataset.filter, el.value); } else { url.searchParams.delete(el.dataset.filter); }
            url.searchParams.delete('page');
            window.location.assign(url.toString());
        });
    });
</script>
@endpush
