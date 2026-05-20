<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class PurchaseOrderCostTrendChart extends Chart
{
    protected $title = 'Costo Ejecutado de OCs por Mes (Millones COP)';

    protected $height = 300;

    protected $type = self::TYPE_BAR;

    protected $target = 'purchase_order_cost_trend';

    protected $valuesOverPoints = 1;

    protected $barOptions = [
        'spaceRatio' => 0.5,
        'stacked' => 0,
        'height' => 20,
        'depth' => 2,
    ];

    protected $colors = ['#6610f2'];
}
