<?php

namespace App\Console\Commands;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\PreventiveSchedule;
use App\Services\MaintenanceNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPreventiveSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkmedia:check-preventive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica las matrices preventivas y levanta notificaciones si un espacio requiere mantenimiento pronto.';

    /**
     * Execute the console command.
     */
    public function handle(MaintenanceNotificationService $notificationService)
    {
        $this->info("Iniciando revisión de Mantenimientos Preventivos...");

        // 1. Obtener todas las reglas activas
        $schedules = PreventiveSchedule::where('is_active', true)->get();
        if ($schedules->isEmpty()) {
            $this->warn("No hay reglas preventivas activas configuradas.");
            return;
        }

        // 2. Traer todos los espacios publicitarios
        $spaces = AdvertisingSpace::all();
        $notifiedCount = 0;

        foreach ($spaces as $space) {
            // 3. Buscar la regla aplicable. Priorizar coincidencia de Tipo + Ciudad, luego solo Tipo.
            $rule = $schedules->where('element_type', $space->type)
                              ->where('city', $space->city)
                              ->first();

            if (!$rule) {
                // Buscar regla general solo por tipo
                $rule = $schedules->where('element_type', $space->type)
                                  ->whereNull('city')
                                  ->first();
            }

            if (!$rule) {
                // No hay regla para este espacio
                continue;
            }

            // 4. Calcular Fecha Base (A o B o más reciente)
            // Según lo acordado, la fecha base de cálculo es la más reciente entre:
            // A. Último mantenimiento cerrado
            $lastMaintenance = Maintenance::where('advertising_space_id', $space->id)
                ->where('status', Maintenance::STATUS_CLOSED)
                ->latest('closed_at')
                ->first();
            $maintenanceDate = $lastMaintenance ? $lastMaintenance->closed_at : null;

            // B. Última auditoría BUENA
            $lastGoodAudit = Audit::where('advertising_space_id', $space->id)
                ->where('general_status', 'good')
                ->latest('created_at')
                ->first();
            $auditDate = $lastGoodAudit ? $lastGoodAudit->created_at : null;

            // Encontrar la más reciente
            $baseDate = null;
            if ($maintenanceDate && $auditDate) {
                $baseDate = $maintenanceDate->max($auditDate);
            } elseif ($maintenanceDate) {
                $baseDate = $maintenanceDate;
            } elseif ($auditDate) {
                $baseDate = $auditDate;
            }

            if (!$baseDate) {
                // Si no hay historial, tomamos la fecha de creación del espacio en el sistema como punto 0
                $baseDate = $space->created_at;
            }

            // 5. Calcular fecha de vencimiento
            $dueDate = $baseDate->copy()->addDays($rule->frequency_days);

            // 6. Verificar si estamos en la ventana de notificación (ej: 30 días antes de vencer)
            $notificationWindowDays = 30; // Podemos hacerlo configurable después, por ahora 1 mes antes
            $notificationDate = $dueDate->copy()->subDays($notificationWindowDays);

            if (now()->greaterThanOrEqualTo($notificationDate)) {
                
                // Evitamos notificar todos los días. 
                // Revisamos si ya existe un mantenimiento preventivo ABIERTO para este espacio
                $hasOpenPreventive = Maintenance::where('advertising_space_id', $space->id)
                    ->where('type', Maintenance::TYPE_PREVENTIVE)
                    ->where('status', '!=', Maintenance::STATUS_CLOSED)
                    ->exists();

                if (!$hasOpenPreventive) {
                    $this->info("Alerta Preventiva para {$space->external_code} - Vence: {$dueDate->format('Y-m-d')}");
                    
                    // Pasar el dueDate como metadata adicional al servicio si es necesario (se puede hacer temporalmente en un atributo)
                    $space->setAttribute('preventive_due_date', $dueDate);
                    $space->setAttribute('preventive_rule_days', $rule->frequency_days);

                    // Despachar evento
                    $notificationService->notify('preventive_reminder', clone $space);
                    $notifiedCount++;
                }
            }
        }

        $this->info("Revisión completada. Se generaron {$notifiedCount} alertas preventivas nuevas.");
    }
}
