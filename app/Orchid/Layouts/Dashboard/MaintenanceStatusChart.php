<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class MaintenanceStatusChart extends Chart
{
    protected $title = 'Novedades por Estado y Categoría';
    protected $height = 300;
    protected $type = 'bar';
    protected $target = 'maintenance_status';
    protected $colors = ['#dc3545', '#198754'];
}
