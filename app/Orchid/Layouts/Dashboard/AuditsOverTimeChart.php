<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class AuditsOverTimeChart extends Chart
{
    protected $title = 'Elementos Auditados (Por Mes)';
    protected $height = 300;
    protected $type = 'line';
    protected $target = 'audits_over_time';
    protected $colors = ['#0d6efd'];
}
