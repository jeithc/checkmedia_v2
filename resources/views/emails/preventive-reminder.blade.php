@extends('emails.layout')

@section('header', 'Mantenimiento Preventivo Requerido')

@section('content')
<p>Hola,</p>

<p>Te notificamos que el espacio publicitario <strong>{{ $space->external_code }}</strong> está próximo a requerir mantenimiento preventivo, basándose en la configuración de <strong>{{ $space->preventive_rule_days }} días</strong> establecida en la matriz.</p>

<div class="data-table">
    <div class="data-row">
        <span class="data-label">Código:</span>
        <span class="data-value">{{ $space->external_code }}</span>
    </div>
    <div class="data-row">
        <span class="data-label">Tipo:</span>
        <span class="data-value">{{ $space->type ?? 'N/A' }}</span>
    </div>
    <div class="data-row">
        <span class="data-label">Ciudad:</span>
        <span class="data-value">{{ $space->city ?? 'N/A' }}</span>
    </div>
    <div class="data-row">
        <span class="data-label">Vencimiento estimado:</span>
        <span class="data-value" style="color: #c60813; font-weight: bold;">
            {{ $space->preventive_due_date ? Carbon\Carbon::parse($space->preventive_due_date)->format('d/m/Y') : 'N/A' }}
        </span>
    </div>
</div>

<p>Por favor, revisa el estado del espacio e inicia las gestiones correspondientes de mantenimiento preventivo si es necesario.</p>

<a href="{{ route('platform.spaces.view', $space->id) }}" class="button" style="display: inline-block; padding: 10px 20px; background-color: #c60813; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;">
    Ver Espacio en Check Media
</a>
@endsection
