<?php
namespace Tests\Feature;
use App\Models\{User,Helper,HelperCompetencyHistory,Session,HelpSeeker};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HelperSidebarStatsTest extends TestCase {
    use RefreshDatabase;
    use \Tests\Concerns\SeekerWorkflowFixtures;
    public function test_status_endpoint_refreshes_sidebar_and_converts_score(): void {
        $user=User::factory()->create(['role'=>'helper','is_active'=>true]);
        $helper=Helper::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Helper','email'=>$user->email,'status'=>'available']);
        $this->verifiedHelperFixture($helper);
        $this->actingAs($user)->getJson(route('helper.readiness.status'))->assertOk()->assertJsonPath('sidebar.totalSessions',0)->assertJsonPath('sidebar.competencyScore',null);
        HelperCompetencyHistory::create(['helper_id'=>$helper->id,'adviser_id'=>$helper->fresh()->adviser_id,'overall_score'=>3,'competency_level'=>2,'evaluation_date'=>now()]);
        $this->getJson(route('helper.readiness.status'))->assertOk()->assertJsonPath('sidebar.competencyScore',60)->assertHeader('Cache-Control','no-store, private');
        config(['app.relax_duty_hours' => true]);
        $helper->update(['availability' => 'break']);
        $this->getJson(route('helper.readiness.status'))->assertOk()->assertJsonPath('sidebar.availabilityLabel', 'On break')->assertJsonPath('sidebar.availabilityStatus', 'unavailable');
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'StatusSeeker']);
        $session = Session::create(['helper_id' => $helper->id, 'seeker_id' => $seeker->id, 'session_status' => 'active', 'session_type' => 'chat', 'risk_level' => 'low', 'created_date' => now()]);
        $this->getJson(route('helper.readiness.status'))->assertOk()->assertJsonPath('sidebar.availabilityLabel', 'In session');
        $session->update(['session_status' => 'completed']);
        $this->getJson(route('helper.readiness.status'))->assertOk()->assertJsonPath('sidebar.availabilityLabel', 'On break');
        $firstSidebar = view('layouts.partials.helper-sidebar')->render();
        $this->assertStringContainsString('On break', $firstSidebar);
        $helper->update(['availability' => 'unavailable']);
        $this->assertStringContainsString('Unavailable', view('layouts.partials.helper-sidebar')->render());
        $other=User::factory()->create(['role'=>'seeker']);
        $this->actingAs($other)->getJson(route('helper.readiness.status'))->assertForbidden();
    }
}
