<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The breathing exercise is the only field in step 1, so it is centred instead of sitting in a side column. */
class HelperReadinessLayoutTest extends TestCase
{
    public function test_breathing_exercise_is_centred_across_the_step(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/helper/readiness.blade.php');

        $this->assertStringContainsString('.rc-breathing { grid-column:1 / -1;', $view);
        $this->assertStringContainsString('align-items:center; text-align:center;', $view);
        $this->assertStringNotContainsString('.rc-breathing { grid-column:3 / span 2; }', $view);
        $this->assertStringNotContainsString('.rc-breathing { grid-column:2;', $view);
    }
}
