<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class PanelLayoutTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    private const string SIMULATED_BROKER_LABEL = 'Simulated broker: no real orders';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_the_login_pages_and_the_panel_layout_show_the_simulated_broker_label(): void
    {
        $this->get(route('login'))->assertOk()->assertSeeText(self::SIMULATED_BROKER_LABEL);

        $this->submitPassword();
        $this->get(route('login.totp'))->assertOk()->assertSeeText(self::SIMULATED_BROKER_LABEL);

        $this->actingAs($this->operatorModel())
            ->get(route('panel.start'))
            ->assertOk()
            ->assertSeeText(self::SIMULATED_BROKER_LABEL);
    }
}
