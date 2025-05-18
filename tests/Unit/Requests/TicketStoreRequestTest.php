<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\TicketStoreRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizeRequiresAuthentication(): void
    {
        Auth::logout();
        $this->assertFalse((new TicketStoreRequest())->authorize());

        $user = User::factory()->create();
        Auth::login($user);
        $this->assertTrue((new TicketStoreRequest())->authorize());
    }

    public function testMissingPaymentUuidFailsValidation(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $rules = (new TicketStoreRequest())->rules();
        $validator = Validator::make([], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_uuid', $validator->errors()->messages());
    }

    public function testInvalidUuidFormatFailsValidation(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $rules = (new TicketStoreRequest())->rules();
        $validator = Validator::make(['payment_uuid' => 'not-a-uuid'], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_uuid', $validator->errors()->messages());
    }

    public function testUuidNotBelongingToUserFailsWithCustomMessage(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        // Create a payment that belongs to someone else
        $other = User::factory()->create();
        $otherPayment = Payment::factory()->create(['user_id' => $other->id]);

        $rules = (new TicketStoreRequest())->rules();
        $validator = Validator::make(
            ['payment_uuid' => $otherPayment->uuid],
            $rules
        );

        $this->assertTrue($validator->fails());
        $msgs = $validator->errors()->get('payment_uuid');
        $this->assertCount(1, $msgs);
        $this->assertStringContainsString(
            "Le paiement spécifié est invalide ou n'appartient pas à cet utilisateur.",
            $msgs[0]
        );
    }

    public function testValidUuidPassesValidation(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $payment = Payment::factory()->create(['user_id' => $user->id]);

        $rules = (new TicketStoreRequest())->rules();
        $validator = Validator::make(
            ['payment_uuid' => $payment->uuid],
            $rules
        );

        $this->assertFalse($validator->fails());
        $this->assertEquals(
            $payment->uuid,
            $validator->validated()['payment_uuid']
        );
    }

    public function testValidatedUuidReturnsExactlyPaymentUuidFromValidated(): void
    {
        // 1) Créez et authentifiez un user
        $user = User::factory()->create();
        Auth::login($user);

        // 2) Créez un paiement pour ce user
        $payment = Payment::factory()->create(['user_id' => $user->id]);

        // 3) Montez la requête avec la donnée valide
        $request = TicketStoreRequest::create(
            '/dummy',
            'POST',
            ['payment_uuid' => $payment->uuid]
        );

        // 4) Injectez le container Laravel pour que authorize() / validation fonctionnent
        $request->setContainer($this->app)
                ->setRedirector($this->app->make('redirect'))
                ->setUserResolver(fn() => $user);

        // 5) Lancez l’autorisation + validation
        $request->validateResolved();

        // 6) Vérifiez enfin que validatedUuid() renvoie bien la valeur validée
        $this->assertSame(
            $payment->uuid,
            $request->validatedUuid()
        );
    }
}
