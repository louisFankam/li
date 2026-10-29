<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $u = User::first();
        $u->email_verified_at = now();
        $u->save();
        return $u;
    }

    public function test_pages_render(): void
    {
        $u = $this->user();
        foreach (['/dashboard','/vente','/stocks','/transfers','/categories','/suppliers','/pharmacies','/users','/customers','/insurers','/assurances','/caisses','/administration'] as $path) {
            $this->actingAs($u)->get($path)->assertStatus(200);
        }
    }

    public function test_login_renders(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
