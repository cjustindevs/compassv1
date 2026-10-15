<?php

namespace Tests\Feature;

use App\Models\{Adviser, EmergencyAlert, Helper, HelpSeeker, Referral, Session, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SeekerReferralDecisionUiTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
    }

    private function referral(string $priority = 'low'): array
    {
        $adviserUser=User::factory()->create(['role'=>'adviser']);
        $adviser=Adviser::create(['user_account_id'=>$adviserUser->id,'first_name'=>'Reviewer','last_name'=>'Adviser','email'=>$adviserUser->email]);
        $helperUser=User::factory()->create(['role'=>'helper']);
        $helper=Helper::create(['user_account_id'=>$helperUser->id,'adviser_id'=>$adviser->id,'first_name'=>'Peer','last_name'=>'Helper','email'=>$helperUser->email]);
        $seeker=User::factory()->create(['role'=>'seeker']);
        $profile=HelpSeeker::create(['user_account_id'=>$seeker->id,'generated_alias'=>'Seeker'.$seeker->id]);
        $this->consentFixture($seeker);
        $session=Session::create(['helper_id'=>$helper->id,'seeker_id'=>$profile->id,'session_status'=>'active','start_time'=>now(),'risk_level'=>$priority]);
        $referral=Referral::create(['session_id'=>$session->id,'helper_id'=>$helper->id,'adviser_id'=>$adviser->id,'status'=>'pending_consent','approved_at'=>now(),
            'priority_level'=>$priority,'referral_reason'=>'Support recommendation <script>unsafe()</script>','referral_date'=>now()]);
        $referral->forceFill(['recommendation_form'=>['session_summary'=>'Summary of the support concern','observations'=>'Detailed observations','actions_taken'=>'Support provided']])->save();
        return [$seeker,$referral];
    }

    private function decisionForm($page, string $decision): \DOMElement
    {
        $document=new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath=new \DOMXPath($document);
        $form=$xpath->query('//form[@data-referral-decision="'.$decision.'"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class,$form);
        $this->assertSame(0,$xpath->query('ancestor::details',$form)->length,'Decision buttons must remain outside collapsed details.');
        return $form;
    }

    public function test_decline_form_sends_zero_and_closes_referral_without_resolving_open_emergency(): void
    {
        [$seeker,$referral]=$this->referral('emergency');
        $alert=EmergencyAlert::create(['session_id'=>$referral->session_id,'seeker_id'=>$referral->session->seeker_id,'adviser_id'=>$referral->adviser_id,'status'=>'triggered','risk_level'=>'emergency','trigger_reason'=>'Test safety review']);
        $page=$this->actingAs($seeker)->get(route('seeker.referrals'))->assertOk()->assertSee('Would you like to proceed?');
        $form=$this->decisionForm($page,'decline');
        $input=(new \DOMXPath($form->ownerDocument))->query('.//input[@name="consent_given"]',$form)->item(0);
        $this->assertSame('0',$input->getAttribute('value'));
        $this->post($form->getAttribute('action'),['consent_given'=>$input->getAttribute('value')])->assertRedirect('/selfhelp')->assertSessionHasNoErrors();
        $this->assertSame('closed',$referral->fresh()->status);
        $this->assertFalse($referral->fresh()->help_seeker_consent);
        $this->assertNotNull($referral->fresh()->consent_declined_at);
        $this->assertDatabaseHas('consent_records',['referral_id'=>$referral->id,'purpose'=>'referral','decision'=>'declined','consent_given'=>false]);
        $this->assertSame('triggered',$alert->fresh()->status);
        $this->assertDatabaseHas('notifications',['user_account_id'=>$referral->adviser->user_account_id,'title'=>'Referral consent updated']);
        $this->get(route('seeker.referrals'))->assertOk()->assertSee('You declined this referral. Your decision was recorded.')->assertDontSee('data-referral-decision=',false);
        $this->post(route('referrals.consent',$referral),['consent_given'=>'0'])->assertConflict();
        $this->assertSame(1,$referral->consentRecords()->where('purpose','referral')->count());
    }

    public function test_agree_form_records_consent_without_automatically_assigning_or_disclosing_identity(): void
    {
        [$seeker,$referral]=$this->referral();
        $page=$this->actingAs($seeker)->get(route('seeker.referrals'))->assertOk();
        $form=$this->decisionForm($page,'agree');
        $input=(new \DOMXPath($form->ownerDocument))->query('.//input[@name="consent_given"]',$form)->item(0);
        $this->assertSame('1',$input->getAttribute('value'));
        $this->post($form->getAttribute('action'),['consent_given'=>$input->getAttribute('value')])->assertRedirect(route('seeker.referrals'))->assertSessionHas('identity_referral_id',$referral->id);
        $this->assertSame('pending_professional',$referral->fresh()->status);
        $this->assertTrue($referral->fresh()->help_seeker_consent);
        $this->assertNull($referral->fresh()->professional_id);
        $this->assertFalse((bool)$referral->fresh()->identity_disclosed);
    }

    public function test_recommendation_is_collapsed_escaped_and_only_owned_referrals_are_visible(): void
    {
        [$seeker,$referral]=$this->referral();
        [$other,$foreign]=$this->referral();
        $foreign->update(['referral_reason'=>'PRIVATE FOREIGN REFERRAL']);
        $page=$this->actingAs($seeker)->get(route('seeker.referrals'))->assertOk()->assertDontSee('PRIVATE FOREIGN REFERRAL')->assertDontSee('Referral #'.$foreign->id)
            ->assertSee('Support recommendation &lt;script&gt;unsafe()&lt;/script&gt;',false)->assertDontSee('<script>unsafe()</script>',false);
        $document=new \DOMDocument;@$document->loadHTML($page->getContent());$xpath=new \DOMXPath($document);
        $this->assertSame(1,$xpath->query('//details[@class="referral-details" and not(@open)]')->length);
        $this->actingAs($other)->post(route('referrals.consent',$referral),['consent_given'=>'0'])->assertForbidden();
        $this->assertSame('pending_consent',$referral->fresh()->status);
    }

    public function test_missing_decision_is_visible_and_never_defaults_to_agreement(): void
    {
        [$seeker,$referral]=$this->referral();
        $this->actingAs($seeker)->from(route('seeker.referrals'))->post(route('referrals.consent',$referral),[])->assertSessionHasErrors('consent_given');
        $this->get(route('seeker.referrals'))->assertOk()->assertSee('The consent given field is required.');
        $this->assertSame('pending_consent',$referral->fresh()->status);
        $this->assertFalse($referral->fresh()->help_seeker_consent);
    }
}
