<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class ComplianceChart extends Chart
{
    protected $title = '% Cumplimiento / Solución';
    protected $height = 250;
    protected $type = 'pie';
    protected $target = 'compliance';
}
