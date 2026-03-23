<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class PurchaseOrderCostTrendChart extends Chart
{
    protected $title = 'Costo Ejecutado de OCs por Mes';

    protected $height = 300;

    protected $type = self::TYPE_LINE;

    protected $target = 'purchase_order_cost_trend';

    protected $lineOptions = [
        'spline' => 1,
        'regionFill' => 1,
        'hideDots' => 0,
        'hideLine' => 0,
        'heatline' => 0,
        'dotSize' => 3,
    ];

    protected $colors = ['#6610f2'];
}
