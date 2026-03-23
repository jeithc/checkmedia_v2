<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class MaintenanceStatusChart extends Chart
{
    protected $title = 'Novedades por Estado y Categoría';
    protected $height = 300;
    protected $type = 'bar';
    protected $target = 'maintenance_status';
    protected $colors = ['#198754', '#dc3545'];
}
