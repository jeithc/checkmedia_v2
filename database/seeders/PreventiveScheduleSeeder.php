<?php

namespace Database\Seeders;

use App\Models\PreventiveSchedule;
use Illuminate\Database\Seeder;

class PreventiveScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = [
            // ESTRUCTURAL: Costa (Consideraremos Barranquilla, Cartagena, Santa Marta como principales, 
            // aunque podemos dejar una por departamento o en su defecto las ciudades literales)
            // Asumiremos las principales de la costa como 1 año (365 días)
            ['element_type' => 'ESTRUCTURAL', 'city' => 'BARRANQUILLA', 'frequency_days' => 365],
            ['element_type' => 'ESTRUCTURAL', 'city' => 'CARTAGENA', 'frequency_days' => 365],
            ['element_type' => 'ESTRUCTURAL', 'city' => 'SANTA MARTA', 'frequency_days' => 365],

            // ESTRUCTURAL: Bogotá (4 Años = 1460 días)
            ['element_type' => 'ESTRUCTURAL', 'city' => 'BOGOTA', 'frequency_days' => 1460],
            
            // ESTRUCTURAL: Cali y Antioquia (Medellín como principal y municipios) - (3 Años = 1095 días)
            ['element_type' => 'ESTRUCTURAL', 'city' => 'CALI', 'frequency_days' => 1095],
            ['element_type' => 'ESTRUCTURAL', 'city' => 'MEDELLIN', 'frequency_days' => 1095],
            // Si hay otras ciudades de Antioquia, se pueden agregar aquí

            // ELÉCTRICO: Anual (General - sin ciudad específica)
            ['element_type' => 'ELECTRICO', 'city' => null, 'frequency_days' => 365],

            // AMBIENTAL: 2 Años (General - sin ciudad específica)
            ['element_type' => 'AMBIENTAL', 'city' => null, 'frequency_days' => 730],
        ];

        foreach ($schedules as $schedule) {
            PreventiveSchedule::updateOrCreate(
                [
                    'element_type' => $schedule['element_type'],
                    'city' => $schedule['city'],
                ],
                [
                    'frequency_days' => $schedule['frequency_days'],
                    'is_active' => true,
                ]
            );
        }
    }
}
