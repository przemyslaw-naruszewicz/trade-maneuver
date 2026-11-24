<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class OperatorSessionTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_an_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get(route('panel.start'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
