<?php
namespace Tests\Feature;
use App\Models\{User, Helper, ReadinessCheck};
use App\Services\HelperDutyCandidates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;
class HelperDutyCandidatesTest extends TestCase
{
    use RefreshDatabase;
    public function test_only_logged_in_currently_ready_helpers_are_candidates(): void
    {
        Schema::create('test_login_sessions', function ($t) { $t->string('id')->primary(); $t->unsignedBigInteger('user_id'); $t->integer('last_activity'); });
        config(['session.driver'=>'database', 'session.table'=>'test_login_sessions', 'session.lifetime'=>120]);
        $user=User::factory()->create(['role'=>'helper','is_active'=>true]);
        $helper=Helper::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Helper','email'=>$user->email,'status'=>'available','availability'=>'available']);
        $check=ReadinessCheck::create(['helper_id'=>$helper->id,'assessment_date'=>now(),'valid_until'=>now()->addHour(),'assessment_result'=>'ready','is_active'=>true]);
        $service=app(HelperDutyCandidates::class);
        $this->assertFalse($service->allows($helper));
        DB::table('test_login_sessions')->insert(['id'=>'login','user_id'=>$user->id,'last_activity'=>now()->timestamp]);
        $this->assertTrue($service->allows($helper));
        $check->update(['assessment_result'=>'not_ready']);
        $this->assertFalse($service->allows($helper));
        $check->update(['assessment_result'=>'ready','valid_until'=>now()->subMinute()]);
        $this->assertFalse($service->allows($helper));
        $check->update(['valid_until'=>now()->addHour()]);
        DB::table('test_login_sessions')->update(['last_activity'=>now()->subMinutes(121)->timestamp]);
        $this->assertFalse($service->allows($helper));
        DB::table('test_login_sessions')->update(['last_activity'=>now()->timestamp]);
        $user->update(['is_active'=>false]);
        $this->assertFalse($service->allows($helper->fresh()));
        $user->update(['is_active'=>true]);
        DB::table('test_login_sessions')->delete();
        $this->assertFalse($service->allows($helper->fresh()));
    }
}
