<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $admin = false): User
    {
        $user = User::create([
            'name' => $admin ? 'Admin User' : 'Regular User',
            'email' => $admin ? 'admin@example.com' : 'user@example.com',
            'password' => 'Password123',
        ]);
        if ($admin) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return $user;
    }

    public function test_public_meta_and_legal_return_defaults(): void
    {
        $expectedTerms = config('neighbours.terms_of_service');
        $expectedPrivacy = config('neighbours.privacy_policy');

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('terms_of_service', $expectedTerms)
            ->assertJsonPath('privacy_policy', $expectedPrivacy);

        $this->getJson('/api/v1/legal')
            ->assertOk()
            ->assertJsonPath('data.terms_of_service', $expectedTerms)
            ->assertJsonPath('data.privacy_policy', $expectedPrivacy);
    }

    public function test_non_admin_cannot_access_or_update_admin_legal_endpoints(): void
    {
        $this->getJson('/api/v1/admin/legal')->assertUnauthorized();
        $this->putJson('/api/v1/admin/legal', ['terms_of_service' => 'Hack'])->assertUnauthorized();

        Sanctum::actingAs($this->makeUser(false));

        $this->getJson('/api/v1/admin/legal')->assertForbidden();
        $this->putJson('/api/v1/admin/legal', ['terms_of_service' => 'Hack'])->assertForbidden();
    }

    public function test_admin_can_update_legal_texts_and_meta_reflects_changes(): void
    {
        Sanctum::actingAs($this->makeUser(true));

        $this->putJson('/api/v1/admin/legal', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terms_of_service');

        $this->putJson('/api/v1/admin/legal', ['terms_of_service' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terms_of_service');

        $this->putJson('/api/v1/admin/legal', [
            'terms_of_service' => 'Updated Terms of Service v2',
            'privacy_policy' => 'Updated Privacy Policy v2',
        ])
            ->assertOk()
            ->assertJsonPath('data.terms_of_service', 'Updated Terms of Service v2')
            ->assertJsonPath('data.privacy_policy', 'Updated Privacy Policy v2');

        $this->getJson('/api/v1/admin/legal')
            ->assertOk()
            ->assertJsonPath('data.terms_of_service', 'Updated Terms of Service v2')
            ->assertJsonPath('data.privacy_policy', 'Updated Privacy Policy v2');

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('terms_of_service', 'Updated Terms of Service v2')
            ->assertJsonPath('privacy_policy', 'Updated Privacy Policy v2');

        $this->getJson('/api/v1/legal')
            ->assertOk()
            ->assertJsonPath('data.terms_of_service', 'Updated Terms of Service v2')
            ->assertJsonPath('data.privacy_policy', 'Updated Privacy Policy v2');
    }
}

