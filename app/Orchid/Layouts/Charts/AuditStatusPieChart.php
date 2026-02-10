<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class AuditStatusPieChart extends Chart
{
    /**
     * Available options:
     * 'bar', 'line',
     * 'pie', 'percentage'.
     *
     * @var string
     */
    protected $type = self::TYPE_PIE;

    /**
     * Height of the chart.
     *
     * @var int
     */
    protected $height = 300;

    /**
     * Colors used for each segment.
     *
     * @var array
     */
    protected $colors = [
        '#28a745', // green for 'good'
        '#dc3545', // red for 'bad'
    ];
}
