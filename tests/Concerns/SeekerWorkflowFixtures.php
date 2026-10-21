<?php
namespace Tests\Concerns;
trait SeekerWorkflowFixtures {
    protected function conversationFixture(\App\Models\Session $session): void {
        foreach (['seeker'=>$session->seeker?->user_account_id, 'helper'=>$session->helper?->user_account_id] as $sender=>$userId) {
            if ($userId) \App\Models\Message::create(['session_id'=>$session->id, 'sender'=>$sender, 'sender_id'=>$userId,
                'message_text'=>'A participant message in this test conversation.', 'sent_datetime'=>now()]);
        }
    }
    protected function verifiedHelperFixture(\App\Models\Helper $helper): void {
        $adviser=$helper->adviser;
        if (!$adviser) {
            $user=\App\Models\User::factory()->create(['role'=>'adviser','is_active'=>true]);
            $adviser=\App\Models\Adviser::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Adviser','email'=>$user->email]);
        }
        $helper->update(['adviser_id'=>$adviser->id,'verification_status'=>'verified','verified_by'=>$adviser->user_account_id,'verified_at'=>now(),'training_verified'=>true,'qualification_evidence'=>'TEST FIXTURE ONLY']);
        $helper->unsetRelations();
    }
    protected function consentFixture(\App\Models\User $user): void {
        foreach(['privacy_policy','informed_consent'] as $purpose) \App\Models\ConsentRecord::create(['seeker_id'=>$user->helpSeeker->id,'document_type'=>$purpose,'purpose'=>$purpose,'version'=>\App\Services\ConsentService::VERSION,'decision'=>'accepted','consent_given'=>true,'consent_date'=>now()]);
    }
    protected function approvedScreeningFixture(): void {
        config(['screening.approved_version'=>\App\Services\ScreeningInstrument::VERSION,'screening.approval_reference'=>'TEST-FIXTURE-ONLY']);
    }
    protected function screeningAnswers(array $overrides=[]): array { return array_replace(array_fill_keys(array_keys(\App\Services\ScreeningInstrument::QUESTIONS),'no'),$overrides); }
    protected function evaluationAnswers(): array { return array_map(fn($a)=>$a[0],\App\Services\EvaluationInstrument::OPTIONS); }
}
