@include('orchid.partials.product-ui-styles')

@php
    $current = \App\Orchid\Filters\RequisitionBatchStatusFilter::current();

    $options = [
        \App\Orchid\Filters\RequisitionBatchStatusFilter::ALL => ['label' => 'Todos', 'dot' => null],
        \App\Orchid\Filters\RequisitionBatchStatusFilter::ACTIVE => ['label' => 'Activos', 'dot' => '#16a34a'],
        \App\Orchid\Filters\RequisitionBatchStatusFilter::CANCELLED => ['label' => 'Cancelados', 'dot' => '#9ca3af'],
    ];
@endphp

<div class="bg-white rounded shadow-sm p-3 mb-3">
    <div class="product-filter-bar">
        <span class="pf-label">Estado</span>

        @foreach($options as $value => $opt)
            <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => null]) }}"
                class="pf-chip {{ $current === $value ? 'active' : '' }}">
                @if($opt['dot'])
                    <span class="pf-dot" style="--pf-dot-color: {{ $opt['dot'] }}"></span>
                @endif
                {{ $opt['label'] }}
            </a>
        @endforeach
    </div>
</div>
