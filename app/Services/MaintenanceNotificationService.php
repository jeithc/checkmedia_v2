<?php

namespace App\Services;

use App\Mail\AuditBadCreatedMail;
use App\Mail\MaintenanceClosedMail;
use App\Mail\MaintenanceRequestedMail;
use App\Mail\PreventiveReminderMail;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\UserNotificationSubscription;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Orchid\Platform\Notifications\DashboardMessage;
use Orchid\Support\Color;

class MaintenanceNotificationService
{
    public function notify(string $eventType, Model $model): void
    {
        $subscriptions = UserNotificationSubscription::where('event_type', $eventType)
            ->with('user')
            ->get();

        $space = $this->resolveSpace($model);
        $notifiedUserIds = [];

        foreach ($subscriptions as $sub) {
            if (!$this->matchesFilter($sub, $space)) continue;
            if (!$sub->user) continue;

            // Una notificación fallida no debe tumbar la acción del usuario (ej. rate limit SMTP)
            try {
                if ($sub->channel === 'email' && $sub->user->email) {
                    $mailable = $this->buildMailable($eventType, $model);
                    Mail::to($sub->user->email)->queue($mailable);
                }

                if (!in_array($sub->user->id, $notifiedUserIds)) {
                    $sub->user->notify($this->buildDashboardMessage($eventType, $model, $space));
                    $notifiedUserIds[] = $sub->user->id;
                }
            } catch (\Throwable $e) {
                Log::warning('Fallo notificando suscripción', [
                    'event_type' => $eventType,
                    'user_id' => $sub->user->id,
                    'channel' => $sub->channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function resolveSpace(Model $model)
    {
        if ($model instanceof Maintenance) {
            return $model->advertisingSpace;
        }

        if ($model instanceof Audit) {
            return $model->space;
        }

        return null;
    }

    protected function matchesFilter($sub, $space): bool
    {
        if (!$space) return $sub->filter_key === 'all';

        return match ($sub->filter_key) {
            'all' => true,
            'business_unit' => $space->business_unit === $sub->filter_value,
            'category' => stripos($space->category ?? '', $sub->filter_value) !== false,
            'element_type' => stripos($space->type ?? '', $sub->filter_value) !== false,
            default => true,
        };
    }

    protected function buildMailable(string $eventType, Model $model): Mailable
    {
        return match ($eventType) {
            'maintenance_requested' => new MaintenanceRequestedMail($model),
            'maintenance_closed' => new MaintenanceClosedMail($model),
            'audit_bad_created' => new AuditBadCreatedMail($model),
            'preventive_reminder' => new PreventiveReminderMail($model),
        };
    }

    protected function buildDashboardMessage(string $eventType, Model $model, $space): DashboardMessage
    {
        $spaceCode = $space->external_code ?? 'N/A';

        return match ($eventType) {
            'maintenance_requested' => DashboardMessage::make()
                ->title("Nueva Novedad — {$spaceCode}")
                ->message("Mantenimiento solicitado: {$model->description}")
                ->action(route('platform.maintenances.detail', $model->id))
                ->type(Color::WARNING),

            'maintenance_closed' => DashboardMessage::make()
                ->title("OC Subsanada — {$spaceCode}")
                ->message("Mantenimiento cerrado. " . ($model->closure_comment ?: 'Sin comentario'))
                ->action(route('platform.maintenances.detail', $model->id))
                ->type(Color::SUCCESS),

            'audit_bad_created' => DashboardMessage::make()
                ->title("Auditoría con Error — {$spaceCode}")
                ->message("Auditoría con novedades: " . ($model->observation ?: 'Sin observación'))
                ->action(route('platform.audit.detail', $model->id))
                ->type(Color::DANGER),

            'preventive_reminder' => DashboardMessage::make()
                ->title("Mantenimiento Preventivo Próximo — {$spaceCode}")
                ->message("El elemento requiere mantenimiento preventivo en aproximadamente {$model->preventive_rule_days} días desde su último arreglo válido.")
                ->action(route('platform.spaces.view', $model->id))
                ->type(Color::WARNING),
        };
    }
}
