<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class PurchaseOrderValueStatusChart extends Chart
{
    protected $title = 'Estado de Valor de OCs';

    protected $height = 300;

    protected $type = self::TYPE_PIE;

    protected $target = 'purchase_order_value_status';

    protected $colors = ['#198754', '#fd7e14', '#6c757d'];
}
