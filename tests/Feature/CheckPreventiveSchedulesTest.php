<?php

namespace Tests\Feature;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\PreventiveSchedule;
use App\Services\MaintenanceNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckPreventiveSchedulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_triggers_alert_when_expiration_is_within_30_days()
    {
        // Regla: 100 días
        PreventiveSchedule::create([
            'element_type' => 'ESTRUCTURAL',
            'city' => 'BOGOTA',
            'frequency_days' => 100,
            'is_active' => true,
        ]);

        // Espacio creado hace 85 días (faltan 15 para los 100, está en ventana secreta de <=30)
        $space = new AdvertisingSpace();
        $space->external_code = 'TEST-EXP-01';
        $space->type = 'ESTRUCTURAL';
        $space->city = 'BOGOTA';
        $space->save();
        
        $space->created_at = now()->subDays(85);
        $space->save();

        // Esperamos que el servicio sea llamado
        $this->mock(MaintenanceNotificationService::class, function (MockInterface $mock) use ($space) {
            $mock->shouldReceive('notify')
                ->once()
                ->with('preventive_reminder', \Mockery::on(function ($arg) use ($space) {
                    return $arg->id === $space->id && $arg->preventive_rule_days == 100;
                }));
        });

        $this->artisan('checkmedia:check-preventive')
            ->expectsOutput("Iniciando revisión de Mantenimientos Preventivos...")
            ->expectsOutputToContain("Alerta Preventiva para {$space->external_code}")
            ->assertExitCode(0);
    }

    public function test_it_does_not_trigger_when_expiration_is_far_away()
    {
        PreventiveSchedule::create([
            'element_type' => 'ESTRUCTURAL',
            'city' => 'BOGOTA',
            'frequency_days' => 100,
            'is_active' => true,
        ]);

        // Espacio creado hace 10 días (faltan 90 para los 100)
        $space = new AdvertisingSpace();
        $space->external_code = 'TEST-SAFE-01';
        $space->type = 'ESTRUCTURAL';
        $space->city = 'BOGOTA';
        $space->save();
        
        $space->created_at = now()->subDays(10);
        $space->save();

        $this->mock(MaintenanceNotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('notify');
        });

        $this->artisan('checkmedia:check-preventive')
            ->expectsOutputToContain("0 alertas")
            ->assertExitCode(0);
    }

    public function test_it_recalculates_base_date_from_good_audit()
    {
        PreventiveSchedule::create([
            'element_type' => 'ESTRUCTURAL',
            'city' => 'BOGOTA',
            'frequency_days' => 100,
            'is_active' => true,
        ]);

        // Creado hace 120 días (Estaría súper vencido)
        $space = new AdvertisingSpace();
        $space->external_code = 'TEST-AUDIT-01';
        $space->type = 'ESTRUCTURAL';
        $space->city = 'BOGOTA';
        $space->save();
        $space->created_at = now()->subDays(120);
        $space->save();

        // PERO tuvo una auditoría BUENA hace 10 días (faltan 90 días ahora)
        $audit = new Audit();
        $audit->advertising_space_id = $space->id;
        $audit->general_status = 'good';
        $audit->audit_date = now()->subDays(10)->toDateString();
        $audit->year = now()->subDays(10)->year;
        $audit->week = now()->subDays(10)->weekOfYear;
        $audit->save();
        $audit->created_at = now()->subDays(10);
        $audit->save();

        $this->mock(MaintenanceNotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('notify');
        });

        $this->artisan('checkmedia:check-preventive')
            ->expectsOutputToContain("0 alertas")
            ->assertExitCode(0);
    }
    /**
     * Review P1 (PR #15): cancelling a batch closes its maintenances with a fresh
     * closed_at but no work was done. The scheduler must not treat that as the
     * last completed maintenance, or a cancellation would silence the reminder
     * for a whole frequency cycle.
     */
    public function test_a_cancelled_batch_maintenance_does_not_reset_the_preventive_cycle()
    {
        PreventiveSchedule::create([
            'element_type' => 'ESTRUCTURAL',
            'city' => 'BOGOTA',
            'frequency_days' => 100,
            'is_active' => true,
        ]);

        // Espacio creado hace 85 días: está en ventana de alerta (faltan 15).
        $space = new AdvertisingSpace();
        $space->external_code = 'TEST-CANCEL-01';
        $space->type = 'ESTRUCTURAL';
        $space->city = 'BOGOTA';
        $space->save();
        $space->created_at = now()->subDays(85);
        $space->save();

        // Un lote cancelado HOY dejó un mantenimiento cerrado hoy, sin trabajo real.
        Maintenance::create([
            'advertising_space_id' => $space->id,
            'type' => Maintenance::TYPE_PREVENTIVE,
            'category' => 'preventivo',
            'status' => Maintenance::STATUS_CLOSED,
            'closure_comment' => Maintenance::CLOSURE_CANCELLED_PREFIX.' Lote cancelado.',
            'closed_at' => now(),
            'requested_at' => now(),
            'description' => 'x',
        ]);

        // La alerta DEBE seguir saliendo: el cancelado no cuenta como mantenimiento hecho.
        $this->mock(MaintenanceNotificationService::class, function (MockInterface $mock) use ($space) {
            $mock->shouldReceive('notify')->once()
                ->with('preventive_reminder', \Mockery::on(fn ($arg) => $arg->id === $space->id));
        });

        $this->artisan('checkmedia:check-preventive')
            ->expectsOutputToContain("Alerta Preventiva para {$space->external_code}")
            ->assertExitCode(0);
    }

    public function test_a_real_closed_maintenance_still_resets_the_preventive_cycle()
    {
        // Regresión del caso normal: un cierre real hoy sí reinicia el ciclo.
        PreventiveSchedule::create([
            'element_type' => 'ESTRUCTURAL',
            'city' => 'BOGOTA',
            'frequency_days' => 100,
            'is_active' => true,
        ]);

        $space = new AdvertisingSpace();
        $space->external_code = 'TEST-REAL-01';
        $space->type = 'ESTRUCTURAL';
        $space->city = 'BOGOTA';
        $space->save();
        $space->created_at = now()->subDays(85);
        $space->save();

        Maintenance::create([
            'advertising_space_id' => $space->id,
            'type' => Maintenance::TYPE_PREVENTIVE,
            'category' => 'preventivo',
            'status' => Maintenance::STATUS_CLOSED,
            'closure_comment' => 'Trabajo realizado.',
            'closed_at' => now(),
            'requested_at' => now(),
            'description' => 'x',
        ]);

        $this->mock(MaintenanceNotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('notify');
        });

        $this->artisan('checkmedia:check-preventive')
            ->expectsOutputToContain('0 alertas')
            ->assertExitCode(0);
    }
}
