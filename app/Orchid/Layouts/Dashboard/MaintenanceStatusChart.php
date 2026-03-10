<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class MaintenanceStatusChart extends Chart
{
    protected $title = 'Novedades por Estado y Categoría';
    protected $height = 250;
    protected $type = 'bar';
    protected $target = 'maintenance_status';
}
