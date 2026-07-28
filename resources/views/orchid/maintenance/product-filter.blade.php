@include('orchid.partials.product-ui-styles')

@php
    $currentProduct = request('product');

    $units = collect(\App\Models\AdvertisingSpace::BUSINESS_UNITS)
        ->mapWithKeys(fn ($value) => [$value => \App\Models\AdvertisingSpace::businessUnitMeta($value)]);
@endphp

<div class="bg-white rounded shadow-sm p-3 mb-3">
    <div class="product-filter-bar">
        <span class="pf-label">Producto</span>

        <a href="{{ request()->fullUrlWithQuery(['product' => null, 'page' => null]) }}"
            class="pf-chip {{ $currentProduct ? '' : 'active' }}">
            Todos
        </a>

        @foreach($units as $value => $unit)
            <a href="{{ request()->fullUrlWithQuery(['product' => $value, 'page' => null]) }}"
                class="pf-chip {{ $currentProduct === $value ? 'active' : '' }}"
                title="{{ $value }}">
                <span class="pf-dot {{ $unit['hollow'] ? 'hollow' : '' }}" style="--pf-dot-color: {{ $unit['color'] }}"></span>
                {{ $unit['label'] }}
            </a>
        @endforeach
    </div>
</div>
