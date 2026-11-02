<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_is_denied_management_pages_but_can_sell(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA);

        $this->get('/vente')->assertOk();
        $this->get('/caisses')->assertOk();
        $this->get('/achats')->assertForbidden();
        $this->get('/inventaires')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/audit')->assertForbidden();
    }

    public function test_manager_has_operations_access_but_not_audit(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager);

        $this->get('/caisses')->assertOk();
        $this->get('/achats')->assertOk();
        $this->get('/users')->assertOk();
        $this->get('/audit')->assertForbidden();
    }

    public function test_regional_manager_can_audit_but_not_operate_cash(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->regional);

        $this->get('/audit')->assertOk();
        $this->get('/caisses')->assertForbidden();
    }
}
