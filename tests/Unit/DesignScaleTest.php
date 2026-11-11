<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source-level guard for the DESIGN.md rules that are easy to break by hand:
 *  - font sizes must be on the type scale (11/12/13/14/16/18/20/24/28px)
 *  - no colored "glow" shadows (a colored box-shadow with a real blur)
 *
 * Each area is listed in CLEAN_AREAS once its stylesheets and views follow the rules.
 * Exempt: e-mail templates (mail clients), PDF/print exports (dompdf), and any line marked
 * with a "scale-exempt" comment. The landing page may use display sizes above 28px.
 */
class DesignScaleTest extends TestCase
{
    private const TYPE_SCALE = [11, 12, 13, 14, 16, 18, 20, 24, 28];

    private const CLEAN_AREAS = ['shared', 'admin', 'moderator', 'adviser', 'helper'];

    public static function areas(): array
    {
        return array_combine(self::CLEAN_AREAS, array_map(fn ($a) => [$a], self::CLEAN_AREAS)) ?: ['none' => ['none']];
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Which role area a stylesheet or view belongs to. */
    public static function areaOf(string $path): ?string
    {
        $p = str_replace('\\', '/', $path);
        if (preg_match('#views/emails/|export\.blade\.php$|pdf#i', $p)) {
            return null;
        }
        $rules = [
            'admin' => '#admin-dashboard\.css|views/admin/|views/components/admin/|views/dashboard/admin#',
            'moderator' => '#moderator-operations\.css|views/moderator/|views/dashboard/moderator#',
            'adviser' => '#adviser-[a-z-]+\.css|views/adviser/|views/concerns/|views/dashboard/adviser|components/adviser-#',
            'helper' => '#helper-refinement\.css|helper-components\.css|views/helper/|views/dashboard/helper|components/(rating-explainer|eligibility-check|helper-capacity|evaluation-steps)#',
            'professional' => '#professional-referral\.css|views/professional/|views/dashboard/professional#',
            'seeker' => '#seeker-request\.css|selfhelp-mobile\.css|views/(dashboard/seeker|request/|session/|selfhelp/|seeker/|settings/|profile/|emergency\.blade)|components/seeker-#',
        ];
        foreach ($rules as $area => $pattern) {
            if (preg_match($pattern, $p)) {
                return $area;
            }
        }

        return 'shared';
    }

    /** @return list<string> */
    private static function files(string $area): array
    {
        $files = [];
        foreach (['public/css', 'resources/css', 'resources/views'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root().'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if (preg_match('/\.(css|php)$/', $path) && self::areaOf($path) === $area) {
                    $files[] = $path;
                }
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<string> human-readable violations for one file */
    public static function violations(string $path): array
    {
        $found = [];
        $landing = (bool) preg_match('#landing|welcome#', $path);
        foreach (preg_split('/\R/', file_get_contents($path)) as $i => $line) {
            if (str_contains($line, 'scale-exempt')) {
                continue;
            }
            $where = basename($path).':'.($i + 1);
            preg_match_all('/font-size:\s*([0-9.]+)(px|rem)\b/', $line, $sizes, PREG_SET_ORDER);
            foreach ($sizes as [$raw, $value, $unit]) {
                $px = $unit === 'rem' ? (float) $value * 16 : (float) $value;
                if (! in_array($px, self::TYPE_SCALE, false) && ! ($landing && $px > 28)) {
                    $found[] = "{$where} off-scale {$raw}";
                }
            }
            // Fluid sizes drift between scale steps; only the landing page may use them.
            if (! $landing && preg_match_all('/font-size:\s*(clamp|min|max|calc)\(/', $line, $fluid)) {
                foreach ($fluid[0] as $raw) {
                    $found[] = "{$where} off-scale fluid {$raw}";
                }
            }
            // Tailwind: arbitrary sizes off the scale, and display sizes outside the landing page.
            preg_match_all('/(?<![\w-])text-\[([0-9.]+)px\]|(?<![\w-])text-([3-9]xl)\b/', $line, $tw, PREG_SET_ORDER);
            foreach ($tw as $hit) {
                $arbitrary = $hit[1] ?? '';
                if ($arbitrary !== '' ? ! in_array((float) $arbitrary, self::TYPE_SCALE, false) : ! $landing) {
                    $found[] = "{$where} off-scale {$hit[0]}";
                }
            }
            preg_match_all('/box-shadow:([^;"}]*)/', $line, $shadows);
            foreach ($shadows[1] as $shadow) {
                foreach (preg_split('/,(?![^(]*\))/', $shadow) as $part) {
                    // A brand/accent variable used as a shadow color is a glow too.
                    $accentVar = (bool) preg_match('/var\(--[a-z-]*(accent|green|ring)[a-z-]*\)/', $part);
                    if (! $accentVar && ! preg_match('/rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)/', $part, $rgb)) {
                        continue;
                    }
                    $lengths = preg_match_all('/(?<![\w#.-])(-?[0-9.]+)(?:px)?(?![\w%])/', preg_replace('/(rgba?|var)\([^)]*\)/', '', $part), $m) ? $m[1] : [];
                    $blur = (float) ($lengths[2] ?? 0);
                    $spread = $accentVar ? 255 : max($rgb[1], $rgb[2], $rgb[3]) - min($rgb[1], $rgb[2], $rgb[3]);
                    if ($blur >= 10 && $spread > 60) {
                        $found[] = "{$where} glow shadow".trim($shadow);
                        break;
                    }
                }
            }
        }

        return $found;
    }

    #[DataProvider('areas')]
    public function test_area_follows_the_type_scale_and_shadow_rules(string $area): void
    {
        if ($area === 'none') {
            $this->markTestSkipped('No area is marked clean yet.');
        }
        $files = self::files($area);
        $this->assertNotEmpty($files, "No files found for area {$area}.");

        $violations = array_merge(...array_map(self::violations(...), $files));

        $this->assertSame([], $violations, "Area '{$area}' breaks DESIGN.md:\n".implode("\n", $violations));
    }

    public function test_detector_flags_off_scale_sizes_and_glows_but_not_focus_rings(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'scale').'.css';
        file_put_contents($tmp, implode("\n", [
            '.a { font-size: 15px; }',
            '.b { font-size: 14px; box-shadow: 0 0 0 3px rgba(4,160,82,.15); }',
            '.c { box-shadow: 0 8px 28px rgba(4,160,82,0.3); }',
            '.d { box-shadow: 0 8px 24px rgb(22 59 45 / 12%); font-size: .875rem; }',
            '.e { font-size: 40px; } /* scale-exempt */',
            '<p class="text-[10px] text-[12px] text-sm text-3xl">',
            '.g { box-shadow: 0 8px 20px var(--admin-accent-ring); } .h { box-shadow: 0 0 0 3px var(--admin-accent-ring); }',
            '.i { font-size: clamp(24px, 2vw, 31px); }',
        ]));

        $found = self::violations($tmp);
        unlink($tmp);

        $this->assertCount(6, $found);
        $this->assertStringContainsString(':7 glow', $found[4]);
        $this->assertStringContainsString(':8 off-scale fluid', $found[5]);
        $this->assertStringContainsString(':1 off-scale', $found[0]);
        $this->assertStringContainsString(':3 glow', $found[1]);
        $this->assertStringContainsString(':6 off-scale text-[10px]', $found[2]);
        $this->assertStringContainsString(':6 off-scale text-3xl', $found[3]);
    }
}
