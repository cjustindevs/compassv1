<?php

namespace Tests\Feature;

use App\Models\{Adviser, EmergencyAlert, HelpSeeker, Helper, Session, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfirmedSupervisionCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function records(string $helperSurname = 'Dancel'): array
    {
        $oldUser = User::factory()->create(['role'=>'adviser']);
        $old = Adviser::forceCreate(['id'=>3,'user_account_id'=>$oldUser->id,'first_name'=>'Marcus','last_name'=>'Reviewer','email'=>$oldUser->email]);
        $newUser = User::factory()->create(['role'=>'adviser','is_active'=>true]);
        $new = Adviser::forceCreate(['id'=>5,'user_account_id'=>$newUser->id,'first_name'=>'Lebron','last_name'=>'James','email'=>$newUser->email]);
        $user = User::factory()->create(['role'=>'helper']);
        $helper = Helper::forceCreate(['id'=>16,'user_account_id'=>$user->id,'first_name'=>'Lean','last_name'=>$helperSurname,'email'=>$user->email,'adviser_id'=>$old->id]);
        return [$helper,$old,$new];
    }

    public function test_confirmed_identity_changes_primary_supervision_once_without_rewriting_case_reviewers(): void
    {
        [$helper,$old,$new] = $this->records();
        $seeker = HelpSeeker::create(['user_account_id'=>User::factory()->create(['role'=>'seeker'])->id,'generated_alias'=>'CorrectionTest']);
        $session = Session::create(['seeker_id'=>$seeker->id,'helper_id'=>$helper->id,'session_status'=>'active','session_type'=>'chat','created_date'=>now()]);
        $alert = EmergencyAlert::create(['seeker_id'=>$seeker->id,'session_id'=>$session->id,'adviser_id'=>$old->id,'trigger_reason'=>'Existing coordinator','status'=>'notified']);
        $migration = require database_path('migrations/2026_10_23_000002_correct_confirmed_helper_supervision.php');
        $migration->up();
        $this->assertSame($new->id,$helper->fresh()->adviser_id);
        $this->assertSame($old->id,$alert->fresh()->adviser_id);
        $this->assertSame(1,DB::table('adviser_helper_assignments')->where('helper_id',16)->whereNull('ended_at')->where('adviser_id',5)->count());
        $count = DB::table('adviser_helper_assignments')->count();
        $migration->up();
        $this->assertSame($count,DB::table('adviser_helper_assignments')->count());
        $migration->down();
        $this->assertSame($new->id,$helper->fresh()->adviser_id);
    }

    public function test_same_ids_for_other_accounts_are_not_changed(): void
    {
        [$helper,$old] = $this->records('Unrelated');
        $migration = require database_path('migrations/2026_10_23_000002_correct_confirmed_helper_supervision.php');
        $migration->up();
        $this->assertSame($old->id,$helper->fresh()->adviser_id);
    }
}
