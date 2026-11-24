<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicRoutesTest extends TestCase
{
    public function test_no_route_is_named_for_registration_or_e_mail_password_reset(): void
    {
        foreach (['register', 'password.request', 'password.email', 'password.reset', 'password.update', 'password.confirm'] as $name) {
            $this->assertFalse(Route::has($name), sprintf('The route "%s" exists.', $name));
        }
    }

    public function test_no_route_path_offers_registration_or_a_password_reset(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('/regist|forgot|reset|password/i', $route->uri());
        }
    }

    #[DataProvider('registrationAndPasswordResetPaths')]
    public function test_a_registration_or_password_reset_path_is_not_found(string $method, string $path): void
    {
        $this->call($method, $path)->assertNotFound();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function registrationAndPasswordResetPaths(): iterable
    {
        yield 'registration form' => ['GET', '/register'];
        yield 'registration' => ['POST', '/register'];
        yield 'password reset request form' => ['GET', '/forgot-password'];
        yield 'password reset request' => ['POST', '/forgot-password'];
        yield 'password reset form' => ['GET', '/reset-password/a-token'];
        yield 'password reset' => ['POST', '/reset-password'];
    }
}
