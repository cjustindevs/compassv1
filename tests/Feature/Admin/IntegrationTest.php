<?php
namespace Tests\Feature\Admin;
use App\Models\{User,Adviser,Helper,HelpSeeker,Moderator,PsychologyProfessional,SystemAdministrator,AuditLog};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class IntegrationTest extends TestCase {
    use RefreshDatabase;
    public function test_dashboard_uses_live_totals_and_existing_login(): void {
        $admin=User::factory()->create(['role'=>'admin','password'=>'StrongPass!2026']);
        $this->post('/login',['email'=>$admin->email,'password'=>'StrongPass!2026'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('primaryStats',fn($stats)=>$stats[0]['value']==='1')->assertDontSee('612 GB')->assertDontSee('486');
        $this->assertDatabaseHas('audit_logs',['action'=>'ADMIN_LOGIN_SUCCEEDED','user_account_id'=>$admin->id]);
    }
    public function test_all_imported_pages_block_every_non_admin_role(): void {
        foreach(['seeker','helper','moderator','adviser','professional'] as $role) {
            $user=User::factory()->create(['role'=>$role]);
            foreach(['dashboard','users','roles-permissions','resource-library','audit-logs','backup-restore','system-health','reports','settings'] as $page) $this->actingAs($user)->get(route('admin.'.$page))->assertForbidden();
        }
    }
    public function test_provisioning_creates_required_profiles_without_clinical_approval(): void {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        foreach(['adviser'=>Adviser::class,'helper'=>Helper::class,'moderator'=>Moderator::class,'professional'=>PsychologyProfessional::class,'admin'=>SystemAdministrator::class,'seeker'=>HelpSeeker::class] as $role=>$model) {
            $this->post(route('admin.users.store'),['first_name'=>'New','last_name'=>'Account','email'=>$role.'@example.test','role'=>$role,'account_status'=>'active'])->assertSessionHasNoErrors()->assertRedirect();
            $account=User::where('email',$role.'@example.test')->firstOrFail();
            $this->assertTrue($model::where('user_account_id',$account->id)->exists());
            if($role==='helper') $this->assertNotEquals('verified',$account->helper->verification_status);
        }
    }
    public function test_audit_page_does_not_disclose_sensitive_operational_narratives(): void {
        $admin=User::factory()->create(['role'=>'admin']);
        AuditLog::create(['user_account_id'=>$admin->id,'action'=>'risk_reassessed','module'=>'sessions','description'=>'PRIVATE CLINICAL SENTINEL']);
        $this->actingAs($admin)->get(route('admin.audit-logs'))->assertOk()->assertDontSee('PRIVATE CLINICAL SENTINEL')->assertSee('Protected operational event');
    }
    public function test_inactive_administrator_cannot_open_admin_pages(): void {
        $this->actingAs(User::factory()->create(['role'=>'admin','is_active'=>false]))->get(route('admin.users'))->assertRedirect(route('login'));
    }
}
