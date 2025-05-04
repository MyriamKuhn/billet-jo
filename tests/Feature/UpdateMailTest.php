<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use App\Notifications\VerifyNewEmailNotification;
use App\Notifications\EmailUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function testItReturnsUnauthorizedIfUserIsNotAuthenticated()
    {
        $response = $this->patchJson('/api/auth/update-email', [
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Authentication required',
                'code' => 'unauthenticated',
            ]);
    }

    public function testItReturnsValidationErrorIfEmailIsInvalid()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/auth/update-email', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testItReturnsErrorIfEmailIsAlreadyUsed()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/auth/update-email', [
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testItReturnsErrorIfEmailIsSameAsCurrent()
    {
        $user = User::factory()->create(['email' => 'current@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/auth/update-email', [
            'email' => 'current@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function testItCreatesEmailUpdateAndSendsNotifications()
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/auth/update-email', [
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => __('validation.email_sent_new_email'),
            ]);

        $this->assertDatabaseHas('email_updates', [
            'user_id' => $user->id,
            'old_email' => 'old@example.com',
            'new_email' => 'new@example.com',
        ]);

        Notification::assertSentTo($user, EmailUpdatedNotification::class);
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            VerifyNewEmailNotification::class
        );
    }

    public function testUpdateEmailUnauthorizedWhenUserIsNull()
    {
        $this->withoutMiddleware(); // désactive Sanctum

        $response = $this->patchJson('/api/auth/update-email', [
            'email' => 'newemail@example.com',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => __('validation.error_unauthorized'),
            ]);
    }

    public function testVerifyNewEmailWithoutToken()
    {
        // Créer un utilisateur avec la Factory
        $user = User::factory()->create();

        // Simuler l'authentification de l'utilisateur
        $this->actingAs($user, 'api');

        // Générer une URL signée (en l'absence de token pour tester)
        $url = URL::signedRoute('verification.verify.new.email', ['token' => '']);  // Passer un token vide pour le test

        // Effectuer la requête GET avec l'URL signée
        $response = $this->getJson($url);

        // Vérifier que le code de statut est bien 400 et que l'erreur est la bonne
        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'error' => __('validation.error_email_verification_invalid'),
                'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid',
            ]);
    }

    public function testEmailUpdatedNotification()
    {
        // Créer un utilisateur fictif
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
        ]);

        // Mock la notification pour vérifier l'envoi
        Notification::fake();

        // Créer la notification
        $newEmail = 'newemail@example.com';
        $rawToken = 'random-token';

        // Envoyer la notification
        $user->notify(new EmailUpdatedNotification($newEmail, $user->email, $rawToken));

        // Vérifier que la notification a bien été envoyée
        Notification::assertSentTo(
            [$user], EmailUpdatedNotification::class
        );
    }

    public function testEmailContent()
    {
        // Créer un utilisateur fictif
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
        ]);

        // Mock la notification pour vérifier l'email
        Notification::fake();

        $newEmail = 'newemail@example.com';
        $rawToken = 'random-token';

        // Envoyer la notification
        $user->notify(new EmailUpdatedNotification($newEmail, $user->email, $rawToken));

        // Vérifier que l'email a bien été envoyé avec la bonne vue
        Notification::assertSentTo(
            [$user],
            EmailUpdatedNotification::class,
            function ($notification) use ($newEmail, $user) {
                // Vérifier que le contenu de l'email est correct
                $mail = $notification->toMail($user);
                $this->assertStringContainsString($newEmail, $mail->viewData['newEmail']);
                $this->assertEquals(__('mail.subject_email_update_request_cancel', ['app_name'=> env('APP_NAME')]), $mail->subject);

                return true;
            }
        );
    }

    public function testVerificationUrlGeneration()
    {
        $token = 'random-token';
        $user = User::factory()->create(); // Créer un utilisateur fictif

        // Créer la notification
        $notification = new VerifyNewEmailNotification($token);

        // Récupérer l'email généré par la notification
        $mail = $notification->toMail($user);

        // Récupérer l'URL depuis les données de la vue
        $url = $mail->viewData['url'];

        // Créer un objet Request à partir de l'URL
        $request = Request::create($url);

        // Vérifier que l'URL contient le token
        $this->assertStringContainsString($token, $url);

        // Vérifier que l'URL est valide (en utilisant la signature)
        $this->assertTrue(URL::hasValidSignature($request));
    }

    public function testNotificationChannels()
    {
        $token = 'random-token';
        $user = User::factory()->create(); // Créer un utilisateur fictif

        // Créer la notification
        $notification = new VerifyNewEmailNotification($token);

        // Vérifier que le canal utilisé est bien 'mail'
        $channels = $notification->via($user);
        $this->assertContains('mail', $channels);
    }

    public function testVerificationUrlRoute()
    {
        $token = 'random-token';
        $user = User::factory()->create(); // Créer un utilisateur fictif

        // Créer la notification
        $notification = new VerifyNewEmailNotification($token);

        // Récupérer le mail généré par la notification
        $mail = $notification->toMail($user);

        // Récupérer l'URL depuis les données de la vue
        $url = $mail->viewData['url'];

        // Créer une requête simulée avec l'URL pour vérifier la signature
        $request = Request::create($url);

        // Vérifier la signature de l'URL avec la requête simulée
        $this->assertTrue(URL::hasValidSignature($request));
    }

    public function testUpdateEmailHandlesExceptionGracefully()
    {
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
        ]);

        $this->actingAs($user);
        $data = ['email' => 'newemail@example.com'];

        // Simule une exception quand Laravel tente de faire une requête UPDATE/INSERT sur email_updates
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'email_updates')) {
                throw new \Exception('Simulated DB failure');
            }
        });

        $response = $this->patchJson('/api/auth/update-email', $data);

        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'message' => __('validation.error_unknown'),
        ]);
    }

    public function testToArrayReturnsEmptyArrayVerifyNewEmail()
    {
        $notification = new VerifyNewEmailNotification('fake-token');
        $user = User::factory()->make();

        $this->assertEquals([], $notification->toArray($user));
    }
}
