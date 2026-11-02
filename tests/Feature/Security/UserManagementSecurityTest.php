<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class UserManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_only_lists_users_and_roles_inside_his_scope(): void
    {
        $scenario = PharmacyScenario::create();

        $response = $this->actingAs($scenario->manager)->get('/users')->assertOk();
        $props = $response->inertiaProps();

        $this->assertCount(1, $props['users']);
        $this->assertSame($scenario->sellerA->email, $props['users'][0]['email']);
        $this->assertTrue($props['users'][0]['can_update']);
        $this->assertCount(1, $props['pharmacies']);
        $this->assertSame($scenario->pharmacyA->id, $props['pharmacies'][0]['id']);

        $roles = $props['roles_by_pharmacy'][(string) $scenario->pharmacyA->id];
        $this->assertContains('vendeur', $roles);
        $this->assertNotContains('responsable_regional', $roles);
        $this->assertNotContains('administrateur', $roles);
    }

    public function test_manager_cannot_update_a_user_from_another_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->put("/users/{$scenario->sellerB->id}", [
                'name' => 'Intrusion',
                'email' => 'intrusion@example.test',
                'pharmacies' => [['id' => $scenario->pharmacyB->id, 'role' => 'vendeur']],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $scenario->sellerB->id,
            'email' => $scenario->sellerB->email,
        ]);
    }

    public function test_manager_cannot_assign_an_out_of_scope_pharmacy_or_privileged_role(): void
    {
        $scenario = PharmacyScenario::create();

        $cases = [
            [['id' => $scenario->pharmacyB->id, 'role' => 'vendeur'], 'pharmacies.0.role'],
            [['id' => $scenario->pharmacyA->id, 'role' => 'responsable_regional'], 'pharmacies.0.role'],
            [['id' => $scenario->pharmacyA->id, 'role' => 'administrateur'], 'pharmacies.0.role'],
        ];

        foreach ($cases as $index => [$assignment, $errorKey]) {
            $email = "blocked-{$index}@example.test";

            $this->actingAs($scenario->manager)
                ->post('/users', [
                    'name' => 'Utilisateur interdit',
                    'email' => $email,
                    'password' => 'password123',
                    'pharmacies' => [$assignment],
                ])
                ->assertRedirect()
                ->assertSessionHasErrors($errorKey);

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_manager_can_create_a_user_on_his_own_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/users', [
                'name' => 'Nouveau Vendeur',
                'email' => 'nouveau-vendeur@example.test',
                'password' => 'password123',
                'pharmacies' => [['id' => $scenario->pharmacyA->id, 'role' => 'vendeur']],
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'nouveau-vendeur@example.test')->firstOrFail();
        $this->assertTrue($created->pharmacies()->whereKey($scenario->pharmacyA->id)->exists());
        $this->assertSame('vendeur', $created->roleFor($scenario->pharmacyA));
    }

    public function test_super_admin_without_pharmacy_pivot_sees_every_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $this->assertFalse($scenario->admin->pharmacies()->exists());

        $response = $this->actingAs($scenario->admin)
            ->withSession(['active_pharmacy_id' => $scenario->pharmacyB->id])
            ->get('/users')
            ->assertOk();
        $props = $response->inertiaProps();

        $this->assertSame($scenario->pharmacyB->id, $props['activePharmacyId']);
        $this->assertCount(6, $props['users']);
        $this->assertCount(2, $props['pharmacies']);
        $this->assertContains('administrateur', array_merge(...array_values($props['roles_by_pharmacy'])));
    }

    public function test_explicit_audit_actor_and_pharmacy_override_the_http_session(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)->withSession(['active_pharmacy_id' => $scenario->pharmacyA->id]);
        app(AuditLogger::class)->log(
            'test.explicit_context',
            User::class,
            $scenario->sellerB->id,
            [],
            ['source' => 'test'],
            actor: $scenario->sellerB,
            pharmacy: $scenario->pharmacyB,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.explicit_context',
            'user_id' => $scenario->sellerB->id,
            'pharmacy_id' => $scenario->pharmacyB->id,
        ]);
    }
}
