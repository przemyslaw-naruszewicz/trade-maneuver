<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    public function test_serialized_users_do_not_expose_credentials(): void
    {
        $user = User::factory()->make();
        $serializedUser = $user->toArray();

        $this->assertArrayHasKey('name', $serializedUser);
        $this->assertArrayHasKey('email', $serializedUser);
        $this->assertSame($user->name, $serializedUser['name']);
        $this->assertSame($user->email, $serializedUser['email']);
        $this->assertArrayNotHasKey('password', $serializedUser);
        $this->assertArrayNotHasKey('remember_token', $serializedUser);
    }

    public function test_user_passwords_are_hashed_and_verification_dates_preserve_their_value(): void
    {
        $user = User::factory()->make([
            'password' => 'a-local-test-password',
            'email_verified_at' => '2026-10-26 10:00:00',
        ]);

        $this->assertNotSame('a-local-test-password', $user->password);
        $this->assertTrue(Hash::check('a-local-test-password', $user->password));

        $verifiedAt = $user->email_verified_at;

        $this->assertInstanceOf(CarbonInterface::class, $verifiedAt);
        $this->assertSame('2026-10-26T10:00:00+00:00', $verifiedAt->toIso8601String());
    }
}
