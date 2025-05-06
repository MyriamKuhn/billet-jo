<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\UserService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // On mocke le service dans le conteneur
        $this->mock(UserService::class);
    }

    public function testIndexReturn200AndTheUsers()
    {
        // 1) Création d'un admin
        $userAdmin = User::factory()->create(['role' => 'admin']);

        // 2) Création de 3 utilisateurs en base (ils auront un 'id')
        $fakeUsers = User::factory()
            ->count(3)
            ->create();  // <-- create() persiste ET retourne une Collection de modèles

        // 3) Mock du service pour qu'il renvoie cette Collection de modèles
        $this->mock(UserService::class)
            ->shouldReceive('listAllUsers')
            ->with($userAdmin)
            ->once()
            ->andReturn($fakeUsers);

        // 4) Authentification via Sanctum
        Sanctum::actingAs($userAdmin, ['*']);

        // 5) Appel de l'endpoint
        $response = $this->getJson('/api/users');
        $response->assertOk();

        // 6) Décodage du JSON et assertions explicites
        $data = $response->json();

        // Structure globale
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('users', $data['data']);

        // On doit avoir exactement 3 users
        $this->assertCount(3, $data['data']['users']);

        // Vérifions le premier user
        $first = $data['data']['users'][0];
        $this->assertIsArray($first);

        // Clés attendues
        $expectedKeys = [
            'id',
            'firstname',
            'lastname',
            'email',
            'created_at',
            'updated_at',
            'role',
            'twofa_enabled',
            'email_verified_at',
            'is_active',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $first, "La clé '$key' est manquante dans le premier user");
        }

        // (Optionnel) Vérifier que les valeurs correspondent
        foreach ($fakeUsers->toArray() as $index => $expectedUser) {
            $actualUser = $data['data']['users'][$index];
            $this->assertEquals($expectedUser['id'],            $actualUser['id']);
            $this->assertEquals($expectedUser['firstname'],     $actualUser['firstname']);
            $this->assertEquals($expectedUser['lastname'],      $actualUser['lastname']);
            $this->assertEquals($expectedUser['email'],         $actualUser['email']);
        }
    }

    public function testShowReturn200AndTheUserInfos()
    {
        // 1) Création de l'actor (employé) et de la cible
        $actor  = User::factory()->create(['role' => 'employee']);
        $target = User::factory()->create();

        // 2) Les infos que notre service doit renvoyer
        $info = [
            'firstname' => $target->firstname,
            'lastname'  => $target->lastname,
            'email'     => $target->email,
        ];

        // 3) On mocke getUserInfo en utilisant withArgs,
        //    qui compare par ID plutôt que par identité d'objet
        $this->mock(UserService::class)
            ->shouldReceive('getUserInfo')
            ->withArgs(function ($givenActor, $givenTarget) use ($actor, $target) {
                return $givenActor->id === $actor->id
                    && $givenTarget->id === $target->id;
            })
            ->once()
            ->andReturn($info);

        // 4) Authentification via Sanctum
        Sanctum::actingAs($actor, ['*']);

        // 5) Appel de l’endpoint
        $response = $this->getJson("/api/users/{$target->id}");

        // 6) Assertions
        $response->assertOk()
                ->assertJson([
                    'user' => $info,
                ]);
    }

    public function testUpdateSelfReturn204WithValidDatas()
    {
        // 1) Création de l'utilisateur
        $user = User::factory()->create();

        // 2) Payload valide
        $payload = ['firstname' => 'Paul', 'lastname' => 'Durand'];

        // 3) Mock du service : on renvoie un array pour respecter le type de retour
        $this->mock(UserService::class)
            ->shouldReceive('updateName')
            ->withArgs(function ($givenUser, $givenData) use ($user, $payload) {
                return $givenUser->id === $user->id
                    && $givenData === $payload;
            })
            ->once()
            ->andReturn($payload);  // ← renvoie un array, pas un booléen

        // 4) Authentification via Sanctum
        Sanctum::actingAs($user, ['*']);

        // 5) Appel de l’endpoint et assertion 204 No Content
        $this->patchJson('/api/users/me', $payload)
            ->assertNoContent();
    }


    public function testUpdateSelfReturn422IfInvalidDatas()
    {
        // 1) Création de l’utilisateur
        $user = User::factory()->create();

        // 2) Payload invalide : firstname manquant
        $payload = ['lastname' => 'Dupont'];

        // 3) Authentification via Sanctum
        Sanctum::actingAs($user, ['*']);

        // 4) Appel de l’endpoint et assertions sur la validation
        $this->patchJson('/api/users/me', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['firstname']);
    }

    public function testUpdateAdminReturns204()
    {
        // 1) Création de l'admin et de la cible
        $admin  = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        // 2) Payload
        $payload = [
            'firstname'     => 'Xavier',
            'lastname'      => 'Martin',
            'email'         => 'x.martin@example.com',
            'is_active'     => false,
            'twofa_enabled' => true,
            'role'          => 'employee',
        ];

        // 3) Mock du service avec comparaison par ID et tableau en ==
        $this->mock(UserService::class)
            ->shouldReceive('updateUserByAdmin')
            ->withArgs(function ($givenAdmin, $givenTarget, $givenData) use ($admin, $target, $payload) {
                return $givenAdmin->id === $admin->id
                    && $givenTarget->id === $target->id
                    && $givenData == $payload;  // note l'utilisation de == pour ignorer l’ordre des clés
            })
            ->once()
            ->andReturn([]);  // retourne un array pour respecter la signature

        // 4) Authentification via Sanctum
        Sanctum::actingAs($admin, ['*']);

        // 5) Appel de l’endpoint et assertion 204 No Content
        $this->patchJson("/api/users/{$target->id}", $payload)
            ->assertNoContent();
    }

    public function testCheckEmailUpdateWithDatas()
    {
        // 1) Création de l'admin et de la cible
        $admin  = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        // 2) Données pending
        $pending = [
            'old_email'  => 'old@ex.com',
            'new_email'  => 'new@ex.com',
            'created_at' => now()->subDay()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        // 3) Mock du service, matcher par ID
        $this->mock(UserService::class)
            ->shouldReceive('checkEmailUpdate')
            ->withArgs(function ($givenAdmin, $givenTarget) use ($admin, $target) {
                return $givenAdmin->id === $admin->id
                    && $givenTarget->id === $target->id;
            })
            ->once()
            ->andReturn($pending);

        // 4) Authentification via Sanctum
        Sanctum::actingAs($admin, ['*']);

        // 5) Appel de l’endpoint et assertions
        $this->getJson("/api/users/email/{$target->id}")
            ->assertOk()
            ->assertJson([
                'data'    => $pending,
                'message' => 'Pending email update retrieved',
            ]);
    }

    public function testCheckEmailUpdateWithoutDatas()
    {
        // 1) Création de l'admin et de la cible
        $admin  = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        // 2) Mock du service pour retourner null
        $this->mock(UserService::class)
            ->shouldReceive('checkEmailUpdate')
            ->withArgs(function ($givenAdmin, $givenTarget) use ($admin, $target) {
                return $givenAdmin->id === $admin->id
                    && $givenTarget->id === $target->id;
            })
            ->once()
            ->andReturnNull();

        // 3) Authentification via Sanctum
        Sanctum::actingAs($admin, ['*']);

        // 4) Appel de l’endpoint et assertions
        $this->getJson("/api/users/email/{$target->id}")
            ->assertOk()
            ->assertJson([
                'data'    => null,
                'message' => 'Pending email update not retrieved',
            ]);
    }

    public function testStoreEmployeeReturn201()
    {
        // 1) Création de l'admin
        $admin = User::factory()->create(['role' => 'admin']);

        // 2) Payload complet pour la requête (inclut password_confirmation)
        $payload = [
            'firstname'             => 'Emma',
            'lastname'              => 'Leroy',
            'email'                 => 'emma.leroy@example.com',
            'password'              => 'P@ssw0rd1234263!',
            'password_confirmation' => 'P@ssw0rd1234263!',
        ];

        // 3) On construit le tableau validé : sans password_confirmation
        $validated = Arr::only($payload, ['firstname','lastname','email','password']);

        // 4) Mock du service :
        //    - on vérifie l'admin par ID
        //    - on subset sur les champs passés (sans la confirmation)
        $this->mock(UserService::class)
            ->shouldReceive('createEmployee')
            ->withArgs(function ($givenAdmin, $givenData) use ($admin, $validated) {
                return $givenAdmin->id === $admin->id
                    && $givenData === $validated;
            })
            ->once()
            ->andReturn(User::factory()->make());

        // 5) Authentification via Sanctum
        Sanctum::actingAs($admin, ['*']);

        // 6) Appel de l'endpoint (la confirmation est filtrée par la FormRequest)
        $this->postJson('/api/users/employees', $payload)
            ->assertStatus(201);
    }

    public function testShowSelfReturnProfil()
    {
        // 1) Création de l'utilisateur
        $user = User::factory()->create();

        // 2) Profil attendu
        $profile = [
            'firstname'     => $user->firstname,
            'lastname'      => $user->lastname,
            'email'         => $user->email,
            'twofa_enabled' => $user->twofa_enabled,
        ];

        // 3) Mock du service, on compare uniquement l'ID de l'utilisateur
        $this->mock(UserService::class)
            ->shouldReceive('getSelfInfo')
            ->withArgs(fn($givenUser) => $givenUser->id === $user->id)
            ->once()
            ->andReturn($profile);

        // 4) Authentification via Sanctum
        Sanctum::actingAs($user, ['*']);

        // 5) Appel de l'endpoint et assertions
        $this->getJson('/api/users/me')
            ->assertOk()
            ->assertJson(['user' => $profile]);
    }
}
