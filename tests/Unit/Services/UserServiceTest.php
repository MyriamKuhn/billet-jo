<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmailUpdate;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\Auth\EmailUpdateNotFoundException;
use App\Enums\UserRole;
use Illuminate\Pagination\LengthAwarePaginator;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();
    }

    public function testListAllUsersReturnsCollectionWhenAdmin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // créer quelques autres users
        User::factory()->count(3)->create();

        $result = $this->service->listAllUsers($admin);
        $this->assertCount(4, $result);
        $this->assertContainsOnlyInstancesOf(User::class, $result);
    }

    public function testListAllUsersThrowsWhenNotAdmin()
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->expectException(AuthorizationException::class);
        $this->service->listAllUsers($user);
    }

    public function testGetUserInfoReturnsDataForAdminAndEmployee()
    {
        $target = User::factory()->create();
        foreach (['admin','employee'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $info = $this->service->getUserInfo($actor, $target);
            $this->assertSame([
                'firstname' => $target->firstname,
                'lastname'  => $target->lastname,
                'email'     => $target->email,
            ], $info);
        }
    }

    public function testGetUserInfoThrowsWhenNotAuthorized()
    {
        $actor  = User::factory()->create(['role' => 'user']);
        $target = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        $this->service->getUserInfo($actor, $target);
    }

    public function testUpdateNameReturnsUpdatedName()
    {
        $user = User::factory()->create(['firstname'=>'Foo','lastname'=>'Bar']);
        $out = $this->service->updateName($user, [
            'firstname'=>'Alice','lastname'=>'Durand'
        ]);
        $this->assertSame(['firstname'=>'Alice','lastname'=>'Durand'], $out);
        $this->assertDatabaseHas('users', [
            'id'=>$user->id,
            'firstname'=>'Alice','lastname'=>'Durand'
        ]);
    }

    public function testUpdateNameThrowsTypeErrorWhenNoUser()
    {
        $this->expectException(\TypeError::class);

        /** @var \App\Models\User|null $noUser */
        $noUser = null;

        $this->service->updateName($noUser, ['firstname'=>'X','lastname'=>'Y']);
    }

    public function testUpdateUserByAdminAppliesAllFieldsAndResetsTwofaSecret()
    {
        $admin  = User::factory()->create(['role'=>'admin']);
        $target = User::factory()->create([
            'firstname'=>'Old','lastname'=>'Name',
            'email'=>'old@example.com','role'=>'user',
            'is_active'=>true,'twofa_enabled'=>true,'twofa_secret'=>'secret'
        ]);

        $data = [
            'firstname'=>'New','lastname'=>'Name2',
            'email'=>'new@example.com','role'=>'employee',
            'is_active'=>false,'twofa_enabled'=>false
        ];
        $out = $this->service->updateUserByAdmin($admin, $target, $data);

        $this->assertSame([
            'firstname'     => 'New',
            'lastname'      => 'Name2',
            'email'         => 'new@example.com',
            'is_active'     => false,
            'twofa_enabled' => false,
            // on s’attend à une instance d’enum, pas à une string
            'role'          => $target->role,
        ], $out);

        $this->assertDatabaseHas('users', [
            'id'            => $target->id,
            'twofa_secret'  => null
        ]);
    }

    public function testUpdateUserByAdminThrowsWhenNotAdmin()
    {
        $actor  = User::factory()->create(['role'=>'user']);
        $target = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        $this->service->updateUserByAdmin($actor, $target, []);
    }

    public function testCheckEmailUpdateReturnsNullOrDataAndThrowsWhenNotAdmin()
    {
        $admin  = User::factory()->create(['role'=>'admin']);
        $target = User::factory()->create();

        // aucun EmailUpdate → null
        $this->assertNull($this->service->checkEmailUpdate($admin, $target));

        // créer une mise à jour
        $eu = EmailUpdate::factory()->create([
            'user_id'=>$target->id,
            'old_email'=>'old@e.com','new_email'=>'new@e.com'
        ]);
        $got = $this->service->checkEmailUpdate($admin, $target);
        $this->assertEquals([
            'old_email'=>$eu->old_email,
            'new_email'=>$eu->new_email,
            'created_at'=>$eu->created_at,
            'updated_at'=>$eu->updated_at,
        ], $got);

        // non-admin → exception
        $user = User::factory()->create(['role'=>'user']);
        $this->expectException(AuthorizationException::class);
        $this->service->checkEmailUpdate($user, $target);
    }

    public function testCreateEmployeeCreatesUserAndThrowsWhenNotAdmin()
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $data = [
            'firstname'=>'E','lastname'=>'L',
            'email'=>'e@example.com','password'=>'P@ssword!'
        ];
        $emp = $this->service->createEmployee($admin, $data);
        $this->assertInstanceOf(User::class, $emp);
        $this->assertSame('employee', $emp->role->value);
        $this->assertTrue(Hash::check($data['password'],$emp->password_hash));

        $user = User::factory()->create(['role'=>'user']);
        $this->expectException(AuthorizationException::class);
        $this->service->createEmployee($user, $data);
    }

    public function testGetSelfInfoReturnsProfile()
    {
        $user = User::factory()->create([
            'firstname'=>'A','lastname'=>'B',
            'email'=>'x@y.com','twofa_enabled'=>true
        ]);
        $out = $this->service->getSelfInfo($user);
        $this->assertSame([
            'firstname'=>'A','lastname'=>'B',
            'email'=>'x@y.com','twofa_enabled'=>true
        ], $out);
    }

    public function testThrowsWhenActorNotAdmin(): void
    {
        $actor = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->listAllUsers($actor);
    }

    public function testReturnsAllUsersWithoutFilters(): void
    {
        $actor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        // Create additional users
        $users = User::factory()->count(3)->create();

        $paginator = $this->service->listAllUsers($actor);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        // Includes actor + 3 others
        $this->assertEquals(4, $paginator->total());
    }

    public function testFiltersByFirstname(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);

        $match1 = User::factory()->create(['firstname' => 'Alice']);
        User::factory()->create(['firstname' => 'Bob']);
        $match2 = User::factory()->create(['firstname' => 'Alicia']);

        $filters = ['firstname' => 'Ali'];

        $paginator = $this->service->listAllUsers($actor, $filters);
        $ids = collect($paginator->items())->pluck('id')->all();

        sort($ids);
        $this->assertEqualsCanonicalizing([
            $match1->id,
            $match2->id,
        ], $ids);
    }

    public function testFiltersByLastname(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);

        $match1 = User::factory()->create(['lastname' => 'Smith']);
        User::factory()->create(['lastname' => 'Jones']);
        $match2 = User::factory()->create(['lastname' => 'Smithson']);

        $filters = ['lastname' => 'Smith'];

        $paginator = $this->service->listAllUsers($actor, $filters);
        $ids = collect($paginator->items())->pluck('id')->all();

        sort($ids);
        $this->assertEqualsCanonicalizing([
            $match1->id,
            $match2->id,
        ], $ids);
    }

    public function testFiltersByEmail(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);

        $match = User::factory()->create(['email' => 'unique@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $filters = ['email' => 'unique@example.com'];

        $paginator = $this->service->listAllUsers($actor, $filters);
        $this->assertCount(1, $paginator->items());
        $this->assertEquals($match->id, $paginator->items()[0]->id);
    }

    public function testFiltersByRole(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);

        $match1 = User::factory()->create(['role' => UserRole::User]);
        User::factory()->create(['role' => UserRole::Employee]);
        $match2 = User::factory()->create(['role' => UserRole::User]);

        $filters = ['role' => UserRole::User->value];

        $paginator = $this->service->listAllUsers($actor, $filters);
        $ids = collect($paginator->items())->pluck('id')->all();

        sort($ids);
        $this->assertEqualsCanonicalizing([
            $match1->id,
            $match2->id,
        ], $ids);
    }
}
