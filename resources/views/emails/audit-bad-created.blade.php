@extends('emails.layout')

@section('content')
    <h2 style="color: #c60813; margin-top: 0;">Auditoría con Novedades</h2>

    <p>Se ha registrado una auditoría con errores en los siguientes datos:</p>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">Código Espacio</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->space->external_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Unidad de Negocio</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->space->category ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Tipo Auditoría</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->audit_type === 'structural' ? 'Estructural' : 'General' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Semana</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->year }} — Semana {{ $audit->week }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Observación</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->observation ?: 'Sin observación' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Auditor</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->user->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: bold;">Fecha</td>
            <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">{{ $audit->audit_date?->format('d/m/Y H:i') ?? 'N/A' }}</td>
        </tr>
    </table>

    <p style="margin: 24px 0;">
        <a href="{{ route('platform.audit.detail', $audit) }}" style="display: inline-block; background: #c60813; color: #fff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: bold;">Ver auditoría</a>
    </p>

    <p style="color: #666; font-size: 13px;">Este es un mensaje automático del sistema Check Media.</p>
@endsection
