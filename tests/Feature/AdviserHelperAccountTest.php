<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** QA round 2: Advisers create their own Helpers with recorded proof of eligibility. */
class AdviserHelperAccountTest extends TestCase
{
    use RefreshDatabase;

    private function adviser(): User
    {
        $user = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        Adviser::create(['user_account_id' => $user->id, 'first_name' => 'Ana', 'last_name' => 'Adviser', 'email' => $user->email]);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New', 'last_name' => 'Helper', 'email' => 'new.helper@school.edu',
            'currently_enrolled' => '1', 'recognized_member' => '1', 'training_completed' => '1',
            'qualification_evidence' => 'Student ID 2023-0001 checked with the registrar; training certificate PS-18.',
        ], $overrides);
    }

    public function test_adviser_creates_a_verified_helper_linked_to_them_and_the_helper_is_notified(): void
    {
        $adviserUser = $this->adviser();
        $this->actingAs($adviserUser)->get(route('adviser.helpers.create'))->assertOk()->assertSee('Proof of eligibility');

        $response = $this->post(route('adviser.helpers.store'), $this->payload());
        $response->assertSessionHasNoErrors();
        $helper = Helper::where('email', 'new.helper@school.edu')->firstOrFail();
        $response->assertRedirect(route('adviser.helper.show', $helper->id));

        $this->assertSame($adviserUser->adviser->id, $helper->adviser_id);
        $this->assertSame('verified', $helper->verification_status);
        $this->assertTrue((bool) $helper->training_verified);
        $this->assertSame($adviserUser->id, (int) $helper->verified_by);
        $this->assertSame('helper', $helper->user->role);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $helper->user_account_id, 'title' => 'Welcome to COMPASS']);
        $this->get(route('adviser.helper.show', $helper->id))->assertOk()->assertSee('Eligibility check')->assertSee('Verified and training approved');
    }

    public function test_every_proof_item_is_required(): void
    {
        $this->actingAs($this->adviser())
            ->post(route('adviser.helpers.store'), $this->payload(['training_completed' => null, 'qualification_evidence' => '']))
            ->assertSessionHasErrors(['training_completed', 'qualification_evidence']);
        $this->assertDatabaseMissing('helpers', ['email' => 'new.helper@school.edu']);
    }

    public function test_existing_emails_are_rejected_and_other_roles_cannot_create_helpers(): void
    {
        User::factory()->create(['email' => 'taken@school.edu']);
        $this->actingAs($this->adviser())->post(route('adviser.helpers.store'), $this->payload(['email' => 'taken@school.edu']))->assertSessionHasErrors('email');

        $helperUser = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $this->actingAs($helperUser)->get(route('adviser.helpers.create'))->assertForbidden();
    }
}
