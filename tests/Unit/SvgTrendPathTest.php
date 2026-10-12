<?php

namespace Tests\Unit;

use App\Support\SvgTrendPath;
use PHPUnit\Framework\TestCase;

class SvgTrendPathTest extends TestCase
{
    public function test_sparse_trends_do_not_invent_extra_points(): void
    {
        $this->assertSame('', SvgTrendPath::make([]));
        $this->assertSame('M 60 40', SvgTrendPath::make([[60, 40]]));
        $this->assertSame('M 60 40 L 780 220', SvgTrendPath::make([[60, 40], [780, 220]]));
    }

    public function test_curves_pass_through_recorded_points_without_overshooting_peaks_or_flat_segments(): void
    {
        foreach ([
            [[60, 220], [200, 40], [380, 220], [780, 40]],
            [[52, 222], [152, 222], [252, 32], [352, 32], [452, 222], [552, 222]],
            [[0, 0], [1, 100], [500, 101], [501, 0]],
        ] as $points) {
            preg_match_all('/C ([\d.\-]+) ([\d.\-]+) ([\d.\-]+) ([\d.\-]+) ([\d.\-]+) ([\d.\-]+)/', SvgTrendPath::make($points), $segments, PREG_SET_ORDER);
            $this->assertCount(count($points) - 1, $segments);
            foreach ($segments as $i => $segment) {
                $this->assertEquals($points[$i + 1][0], (float) $segment[5]);
                $this->assertEquals($points[$i + 1][1], (float) $segment[6]);
                $bounded = true;
                for ($sample = 0; $sample <= 50; $sample++) {
                    $t = $sample / 50;
                    $y = (1 - $t) ** 3 * $points[$i][1]
                        + 3 * (1 - $t) ** 2 * $t * (float) $segment[2]
                        + 3 * (1 - $t) * $t ** 2 * (float) $segment[4]
                        + $t ** 3 * (float) $segment[6];
                    $bounded = $bounded && $y >= min($points[$i][1], $points[$i + 1][1]) - 0.001
                        && $y <= max($points[$i][1], $points[$i + 1][1]) + 0.001;
                }
                $this->assertTrue($bounded, 'The curve must stay within the recorded values for its segment.');
            }
        }
    }
}
