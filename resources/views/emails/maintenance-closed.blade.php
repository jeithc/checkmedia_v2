@extends('emails.layout')

@section('content')
    <h2 style="color: #c60813; margin-top: 0;">OC Subsanada — Mantenimiento Cerrado</h2>

    <p>Se ha cerrado el siguiente mantenimiento:</p>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">Código Espacio</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->advertisingSpace->external_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Cerrado por</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->closedBy->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Fecha de Cierre</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->closed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Comentario de Cierre</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $maintenance->closure_comment ?: 'Sin comentario' }}</td>
        </tr>
    </table>

    <p style="color: #666; font-size: 13px;">Este es un mensaje automático del sistema Check Media.</p>
@endsection
