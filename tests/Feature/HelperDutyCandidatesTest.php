<?php
namespace Tests\Feature;
use App\Models\{User, Helper, ReadinessCheck};
use App\Services\HelperDutyCandidates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;
class HelperDutyCandidatesTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;
    public function test_approved_helpers_can_be_scheduled_without_readiness_or_login(): void
    {
        $user=User::factory()->create(['role'=>'helper','is_active'=>true]);
        $helper=Helper::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Helper','email'=>$user->email]);
        $service=app(HelperDutyCandidates::class);$this->assertFalse($service->allows($helper));
        $this->verifiedHelperFixture($helper);$this->assertTrue($service->allows($helper->fresh()));
        ReadinessCheck::create(['helper_id'=>$helper->id,'assessment_date'=>now(),'valid_until'=>now()->subMinute(),'assessment_result'=>'not_ready','is_active'=>true]);
        $this->assertTrue($service->allows($helper->fresh()));
        $user->update(['is_active'=>false]);$this->assertFalse($service->allows($helper->fresh()));
        $user->update(['is_active'=>true]);$helper->update(['training_verified'=>false]);$this->assertFalse($service->allows($helper->fresh()));
    }
}
