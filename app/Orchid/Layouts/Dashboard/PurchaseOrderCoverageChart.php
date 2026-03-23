<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class PurchaseOrderCoverageChart extends Chart
{
    protected $title = 'Conversión de RQ a OC';

    protected $height = 220;

    protected $type = self::TYPE_PERCENTAGE;

    protected $target = 'purchase_order_coverage';

    protected $colors = ['#0d6efd', '#dee2e6'];
}
