<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phones show every page slightly zoomed out (90%): the layout is 10% wider and scaled to fit,
 * so more fits on screen while the phone breakpoints still apply. Desktop browsers ignore this.
 */
class PhoneViewportTest extends TestCase
{
    public function test_every_page_uses_the_zoomed_out_phone_viewport(): void
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $wrong = [];
        $found = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.blade.php') || str_contains($path, '/emails/')) {
                continue;
            }
            preg_match_all('/<meta name="viewport" content="([^"]*)"/', file_get_contents($path), $m);
            foreach ($m[1] as $content) {
                $found++;
                if (! preg_match('/^width=device-width, ?initial-scale=0\.9(, ?viewport-fit=cover)?$/', $content)) {
                    $wrong[] = substr($path, strlen($root) + 1).': '.$content;
                }
            }
        }

        $this->assertGreaterThan(30, $found);
        $this->assertSame([], $wrong, "Viewport must be width=device-width, initial-scale=0.9:\n".implode("\n", $wrong));
    }
}
