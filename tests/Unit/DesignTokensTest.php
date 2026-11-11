<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * DESIGN.md is the contract for the COMPASS visual system. Every token it documents in its
 * tables must exist in the shared stylesheets, so the guide and the CSS cannot drift apart.
 */
class DesignTokensTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function documentedTokens(): array
    {
        $design = file_get_contents($this->root().'/DESIGN.md');
        preg_match_all('/^\|\s*`(--[a-z0-9-]+)`/m', $design, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_design_md_documents_the_token_scales(): void
    {
        $tokens = $this->documentedTokens();

        foreach (['--space-4', '--text-base', '--radius-lg', '--shadow-1', '--compass-green'] as $expected) {
            $this->assertContains($expected, $tokens, "DESIGN.md should document {$expected}.");
        }
    }

    public function test_every_documented_token_is_defined_in_the_shared_css(): void
    {
        $css = file_get_contents($this->root().'/public/css/compass-ui.css')
            .file_get_contents($this->root().'/public/css/compass-layout.css');

        $missing = array_filter(
            $this->documentedTokens(),
            fn (string $token) => ! preg_match('/'.preg_quote($token, '/').'\s*:/', $css),
        );

        $this->assertSame([], array_values($missing), 'Tokens documented in DESIGN.md but not defined in compass-ui.css.');
    }

    public function test_type_scale_matches_design_md(): void
    {
        $css = file_get_contents($this->root().'/public/css/compass-ui.css');
        $expected = ['--text-2xs' => 11, '--text-xs' => 12, '--text-sm' => 13, '--text-caption' => 14, '--text-base' => 15,
            '--text-md' => 16, '--text-lg' => 18, '--text-xl' => 20, '--text-2xl' => 24, '--text-3xl' => 28,
            '--text-4xl' => 32, '--text-kpi' => 40];

        foreach ($expected as $token => $px) {
            $this->assertMatchesRegularExpression('/'.preg_quote($token, '/').':\s*'.$px.'px;/', $css, "{$token} should be {$px}px.");
        }
    }
}
