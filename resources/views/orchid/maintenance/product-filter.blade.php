@push('stylesheets')
    <style>
        .product-filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }

        .product-filter-bar .pf-label {
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6c757d;
            margin-right: .25rem;
        }

        .pf-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .35rem .85rem;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #fff;
            color: #495057;
            font-size: .8125rem;
            font-weight: 500;
            line-height: 1;
            text-decoration: none;
            transition: border-color .15s ease, box-shadow .15s ease, color .15s ease;
        }

        .pf-chip:hover {
            border-color: #adb5bd;
            color: #212529;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }

        .pf-chip.active {
            background: #212529;
            border-color: #212529;
            color: #fff;
        }

        /* Dot: sólido = estático/físico, hueco = digital */
        .pf-dot {
            width: .5rem;
            height: .5rem;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .pf-dot.hollow {
            background: transparent !important;
            box-shadow: inset 0 0 0 2px var(--pf-dot-color);
        }

        .pf-dot:not(.hollow) {
            background: var(--pf-dot-color);
        }

        .pf-chip.active .pf-dot:not(.hollow) {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, .6);
        }
    </style>
@endpush

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
