<?php
require 'vendor/autoload.php';

use Carbon\Carbon;

$d = Carbon::parse('2025-12-31');
echo "Dec 31, 2025:\n";
echo "  dayOfYear: " . $d->dayOfYear . "\n";
echo "  Current calc: floor((" . $d->dayOfYear . " - 1) / 7) + 1 = " . (floor(($d->dayOfYear - 1) / 7) + 1) . "\n";
echo "  365 / 7 = " . (365 / 7) . "\n";
echo "  364 / 7 = " . (364 / 7) . " (52 weeks exactly)\n";
echo "\nTo get Week 52, we need: floor(364 / 7) = " . floor(364 / 7) . "\n";
