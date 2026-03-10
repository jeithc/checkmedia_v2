<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class AuditsOverTimeChart extends Chart
{
    protected $title = 'Elementos Auditados (Por Mes)';
    protected $height = 250;
    protected $type = 'line';
    protected $target = 'audits_over_time';
}
