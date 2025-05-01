<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Str;
use App\Helpers\EmailHelper;
use App\Models\EmailUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class ResetEMailTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsErrorWhenTokenIsMissing()
    {
        $response = $this->getJson('/api/auth/email/verify-new-mail');

        $response->assertStatus(403);
    }

    public function testItReturnsErrorWhenTokenIsInvalid()
    {
        $response = $this->getJson('/api/auth/email/verify-new-mail?token=invalidtoken123');

        $response->assertStatus(403);
    }

    public function testItReturnsErrorWhenUserDoesNotExist()
    {
        Schema::disableForeignKeyConstraints();

        $rawToken = Str::random(60);
        $hashedToken = EmailHelper::hashToken($rawToken);

        EmailUpdate::factory()->create([
            'user_id' => 999999, // User inexistant
            'token' => $hashedToken,
        ]);

        Schema::enableForeignKeyConstraints();

        $response = $this->getJson("/api/auth/email/verify-new-mail?token=$rawToken");

        $response->assertStatus(403);
    }

    public function testItUpdatesUserEmailSuccessfully()
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $newEmail = 'new@example.com';
        $rawToken = Str::random(60);
        $hashedToken = EmailHelper::hashToken($rawToken);

        EmailUpdate::factory()->create([
            'user_id' => $user->id,
            'old_email' => $user->email,
            'new_email' => $newEmail,
            'token' => $hashedToken,
        ]);

        // Génère l’URL signée correspondant à ta route
        $url = URL::temporarySignedRoute(
            'verification.verify.new.email', // le nom exact de ta route
            now()->addMinutes(60),
            ['token' => $rawToken]
        );

        $response = $this->getJson($url);

        $response->assertStatus(200)
            ->assertJson(['message' => __('validation.email_updated_success')]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $newEmail,
        ]);
    }

    public function testItCancelsEmailUpdateSuccessfully()
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $rawToken = Str::random(60);
        $hashedToken = EmailHelper::hashToken($rawToken);

        // Créer l'enregistrement d'EmailUpdate pour cet utilisateur
        EmailUpdate::factory()->create([
            'user_id' => $user->id,
            'old_email' => 'old@example.com',
            'new_email' => 'new@example.com',
            'token' => $hashedToken,
        ]);

        // Générer l'URL signée pour l'annulation
        $url = URL::temporarySignedRoute(
            'email.update.cancel',
            now()->addHours(48),
            [
                'token' => $rawToken,
                'old_email' => 'old@example.com',
            ]
        );

        // Faire la requête GET avec l'URL signée
        $response = $this->getJson($url);

        // Vérifier la réponse
        $response->assertStatus(200)
            ->assertJson(['message' => __('validation.email_update_canceled')]);

        // Vérifier la base de données
        $this->assertDatabaseMissing('email_updates', [
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
        ]);
    }

    public function testItReturns404IfEmailUpdateRequestNotFound()
    {
        $rawToken = Str::random(60);
        $invalidEmail = 'fake@example.com';

        // Générer une URL signée avec un token invalide
        $url = URL::temporarySignedRoute(
            'email.update.cancel',  // Route pour annuler la mise à jour
            now()->addHours(48),    // L'URL sera valide pendant 48 heures
            [
                'token' => $rawToken,
                'old_email' => $invalidEmail,
            ]
        );

        // Faire la requête GET avec l'URL signée
        $response = $this->getJson($url);

        // Vérifier que l'erreur 404 est renvoyée si la demande de mise à jour de l'email n'existe pas
        $response->assertStatus(404);
    }

    public function testItFailsWithInvalidSignature()
    {
        // Construction d'un lien non signé (donc invalide)
        $rawToken = Str::random(60);
        $oldEmail = 'old@example.com';

        $response = $this->getJson("/api/auth/email/update-cancel/{$rawToken}/{$oldEmail}");

        $response->assertStatus(403);
    }
}
