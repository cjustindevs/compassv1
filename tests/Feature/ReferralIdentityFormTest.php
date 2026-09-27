<?php

namespace Tests\Feature;

use App\Models\Referral;
use Tests\TestCase;

class ReferralIdentityFormTest extends TestCase
{
    public function test_identity_modal_submits_the_current_session_csrf_token(): void
    {
        $this->app['session']->start();
        $this->app['session']->regenerateToken();
        $referral = new Referral;
        $referral->id = 123;
        $html = view('partials.referral-identity-modal', compact('referral'))->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@data-identity-form]');
        $this->assertCount(1, $forms);
        $this->assertSame('POST', $forms->item(0)->getAttribute('method'));
        $tokens = $xpath->query('.//input[@name="_token"]', $forms->item(0));
        $this->assertCount(1, $tokens);
        $this->assertSame(csrf_token(), $tokens->item(0)->getAttribute('value'));
        $this->assertFalse($tokens->item(0)->hasAttribute('disabled'));
    }
}
