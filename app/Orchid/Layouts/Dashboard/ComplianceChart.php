<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class ComplianceChart extends Chart
{
    protected $title = '% Cumplimiento / Solución';
    protected $height = 300;
    protected $type = 'pie';
    protected $target = 'compliance';
    protected $colors = ['#198754', '#dc3545'];
}
