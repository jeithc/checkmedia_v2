@extends('emails.layout')

@section('content')
    <h2 style="color: #c60813; margin-top: 0;">Nueva Novedad de Mantenimiento</h2>

    <p>Se ha registrado una nueva solicitud de mantenimiento con los siguientes datos:</p>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">Código Espacio</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->advertisingSpace->external_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Tipo Mantenimiento</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->type === 'corrective' ? 'Correctivo' : 'Preventivo' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Categoría</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->category_label }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Unidad de Negocio</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->advertisingSpace->category ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Prioridad</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ ucfirst($maintenance->priority ?? 'N/A') }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Descripción</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->description }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Solicitante</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->requestedBy->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Fecha Solicitud</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->requested_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
        </tr>
    </table>

    <p style="color: #666; font-size: 13px;">Este es un mensaje automático del sistema Check Media.</p>
@endsection
