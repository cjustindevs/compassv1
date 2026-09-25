<?php
namespace Tests\Feature;

use App\Models\{ConsentRecord, HelpSeeker, PsychologyProfessional, Referral, ReferralAppointment, Session, User};
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function referral(): Referral
    {
        $user = User::factory()->create(['role'=>'professional','is_active'=>true]);
        $professional = PsychologyProfessional::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Professional','email'=>$user->email,'is_available'=>true]);
        $seekerUser = User::factory()->create(['role'=>'seeker']);
        $seeker = HelpSeeker::create(['user_account_id'=>$seekerUser->id,'generated_alias'=>'TestSeeker','age'=>20,'gender'=>'male']);
        $session = Session::create(['seeker_id'=>$seeker->id,'session_status'=>'completed','risk_level'=>'low','created_date'=>now()]);
        $referral = Referral::create(['session_id'=>$session->id,'professional_id'=>$professional->id,'approved_at'=>now(),'help_seeker_consent'=>true,'status'=>'accepted','referral_reason'=>'Test recommendation','referral_date'=>now()]);
        ConsentRecord::create(['seeker_id'=>$seeker->id,'referral_id'=>$referral->id,'document_type'=>'informed_consent','purpose'=>'referral','version'=>ConsentService::VERSION,'decision'=>'accepted','consent_given'=>true,'consent_date'=>now()]);
        $this->actingAs($user);
        return $referral;
    }

    private function data(): array
    {
        return ['starts_at'=>now('Asia/Manila')->addDays(2)->setTime(10,0)->format('Y-m-d\TH:i'),'ends_at'=>now('Asia/Manila')->addDays(2)->setTime(11,0)->format('Y-m-d\TH:i'),'meeting_details'=>'Meet at the approved guidance office.'];
    }

    public function test_schedule_and_reschedule_preserve_history_and_encrypt_instructions(): void
    {
        $referral = $this->referral();
        $this->post(route('professional.referral.appointment',$referral),$this->data())->assertRedirect()->assertSessionHasNoErrors();
        $first = ReferralAppointment::firstOrFail();
        $this->assertSame($this->data()['meeting_details'],$first->meeting_details);
        $this->assertNotSame($first->meeting_details,$first->getRawOriginal('meeting_details'));
        $this->post(route('professional.referral.appointment',$referral),array_replace($this->data(),['starts_at'=>now('Asia/Manila')->addDays(3)->setTime(10,0)->format('Y-m-d\TH:i'),'ends_at'=>now('Asia/Manila')->addDays(3)->setTime(11,0)->format('Y-m-d\TH:i')]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rescheduled',$first->fresh()->status);
        $this->assertSame($first->id,ReferralAppointment::latest('id')->first()->replaces_id);
    }

    public function test_withdrawn_consent_blocks_scheduling(): void
    {
        $referral = $this->referral();
        $referral->update(['help_seeker_consent'=>false]);
        $this->postJson(route('professional.referral.appointment',$referral),$this->data())->assertStatus(409);
        $this->assertDatabaseCount('referral_appointments',0);
    }

    public function test_overlapping_appointments_for_the_same_professional_are_rejected(): void
    {
        $first = $this->referral();
        $this->post(route('professional.referral.appointment',$first),$this->data())->assertSessionHasNoErrors();
        $second = $first->replicate();
        $second->save();
        $consent = ConsentRecord::where('referral_id',$first->id)->first()->replicate();
        $consent->referral_id = $second->id;
        $consent->save();
        $this->postJson(route('professional.referral.appointment',$second),$this->data())->assertUnprocessable();
        $this->assertDatabaseCount('referral_appointments',1);
    }

    public function test_other_professional_cannot_schedule(): void
    {
        $referral = $this->referral();
        $this->actingAs(User::factory()->create(['role'=>'professional','is_active'=>true]));
        $this->postJson(route('professional.referral.appointment',$referral),$this->data())->assertForbidden();
    }
}
