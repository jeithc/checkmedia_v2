{{-- Logo completo de Efectimedios ("E" + nombre), inline para que herede el color con currentColor.
     Si quien lo usa pasa `class`, reemplaza el tamaño por defecto en lugar de sumarse a él. --}}
@php
    $attrs = $attributes->has('class') ? $attributes : $attributes->merge(['class' => 'h-12 w-auto']);
@endphp
{!! \Illuminate\Support\Str::replaceFirst('<svg ', '<svg '.$attrs->toHtml().' ', file_get_contents(public_path('brand/efectimedios-logo.svg'))) !!}
