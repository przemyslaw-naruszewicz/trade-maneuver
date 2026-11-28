<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\Feature\Trading\Order\Support\OrderDecisions;
use Tests\Feature\Trading\Order\Support\OrdersAwaitingApproval;
use Tests\TestCase;
use TradeManeuver\Trading\Ui\Livewire\Orders;

/**
 * The orders screen across updates while the operator decides: the forms it keeps open, and the messages of decisions
 * that change nothing.
 */
final class OrderScreenDecisionsTest extends TestCase
{
    use OperatorAccounts;
    use OrderDecisions;
    use OrdersAwaitingApproval;
    use RefreshDatabase;

    private const string ALREADY_DECIDED = 'This order was already decided in another tab, so nothing changed. The list below shows its current status.';

    private const string INVALID_LIMIT_PRICE = 'Enter a limit price greater than zero in the order currency, with at most two decimal places.';

    private const string SIGNAL_VALIDITY_ENDED = 'The signal validity of this order has ended, so it can no longer be approved. Nothing changed.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09T11:00:00Z'));
        $this->createEnrolledOperator();
        $this->addTheInvestmentConfiguration();
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
        $this->addOrder('DEMO_B', '2026-11-10T17:00:00Z');
    }

    public function test_after_an_approval_the_next_render_shows_the_new_status_and_a_new_last_update_time(): void
    {
        $screen = $this->screen()->assertSeeText('Live, updated 11:00:00 UTC');

        $this->travelTo(CarbonImmutable::parse('2026-11-09T11:00:30Z'));
        $this->approveOrderOf('DEMO_A');
        $screen->call('$refresh');

        $screen->assertSeeText('Live, updated 11:00:30 UTC')
            ->assertSeeHtml('<span class="status status-approved">Approved</span>')
            ->assertSeeTextInOrder(['Non-terminal orders', 'DEMO_A', 'Approved', 'None, 0 of 98']);
    }

    public function test_an_update_keeps_the_open_forms_with_the_entered_limit_price_and_note(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');
        $demoB = $this->orderIdOf('DEMO_B');
        $screen = $this->screen()
            ->call('openApproval', $demoA)->set('approvals.'.$demoA, '10.60')
            ->call('openRefusal', $demoB)->set('refusals.'.$demoB, 'The spread looks too wide today.');

        $this->addOrder('DEMO_C', '2026-11-10T18:00:00Z');
        $screen->call('$refresh');

        $screen->assertSet('approvals.'.$demoA, '10.60')
            ->assertSet('refusals.'.$demoB, 'The spread looks too wide today.')
            ->assertSeeHtml('aria-label="Approve the order for DEMO_A"')
            ->assertSeeHtml('aria-label="Refuse the order for DEMO_B"')
            ->assertSeeHtml('wire:model="approvals.'.$demoA.'"')
            ->assertSeeHtml('wire:model="refusals.'.$demoB.'"')
            ->assertSeeText('DEMO_C');
    }

    public function test_a_decision_on_an_order_decided_in_another_tab_shows_that_nothing_changed(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');
        $demoB = $this->orderIdOf('DEMO_B');
        $screen = $this->screen()->call('openApproval', $demoA)->call('openRefusal', $demoB);

        $this->refuseOrderOf('DEMO_A');
        $this->approveOrderOf('DEMO_B');
        $screen->set('approvals.'.$demoA, '10.50')->call('approve', $demoA);

        $screen->assertSeeText(self::ALREADY_DECIDED)->assertSeeHtml('role="alert"')->assertDontSeeHtml('aria-label="Approve the order for DEMO_A"');
        $this->assertSame('refused_by_operator', $this->statusOf($demoA));

        $screen->call('refuse', $demoB)->assertSeeText(self::ALREADY_DECIDED);
        $this->assertSame('approved', $this->statusOf($demoB));
    }

    public function test_an_approval_after_signal_validity_ended_shows_that_the_order_can_no_longer_be_approved(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');
        $screen = $this->screen()->call('openApproval', $demoA);

        $this->travelTo(CarbonImmutable::parse('2026-11-10T16:00:00Z'));
        $screen->call('approve', $demoA);

        $screen->assertSeeText(self::SIGNAL_VALIDITY_ENDED)->assertDontSeeText(self::ALREADY_DECIDED);
        $this->assertSame('awaiting_approval', $this->statusOf($demoA));
    }

    public function test_an_approval_at_a_limit_price_that_leaves_zero_quantity_keeps_the_form_open_with_its_message(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');

        $screen = $this->screen()->call('openApproval', $demoA)->set('approvals.'.$demoA, '1000.01')->call('approve', $demoA);

        $screen->assertHasErrors(['approvals.'.$demoA])
            ->assertSeeText('This limit price leaves a quantity of zero, so the order stays awaiting approval. Enter a lower limit price.')
            ->assertSet('approvals.'.$demoA, '1000.01');
        $this->assertSame('awaiting_approval', $this->statusOf($demoA));
    }

    public function test_a_new_limit_price_that_is_not_an_amount_shows_its_message_in_place_of_an_earlier_one(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');
        $screen = $this->screen()->call('openApproval', $demoA)->set('approvals.'.$demoA, '1000.01')->call('approve', $demoA);

        $screen->set('approvals.'.$demoA, '10,50')->call('approve', $demoA);

        $this->assertSame([self::INVALID_LIMIT_PRICE], $screen->errors()->get('approvals.'.$demoA));
        $this->assertSame('awaiting_approval', $this->statusOf($demoA));
    }

    public function test_closing_a_form_discards_the_entered_value_and_its_message(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');
        $demoB = $this->orderIdOf('DEMO_B');
        $screen = $this->screen()
            ->call('openApproval', $demoA)->set('approvals.'.$demoA, '1000.01')->call('approve', $demoA)
            ->call('openRefusal', $demoB)->set('refusals.'.$demoB, 'The spread looks too wide today.');

        $screen->call('close', $demoA)->call('close', $demoB);

        $screen->assertSet('approvals', [])->assertSet('refusals', [])->assertHasNoErrors()
            ->assertDontSeeHtml('aria-label="Approve the order for DEMO_A"')
            ->assertDontSeeHtml('aria-label="Refuse the order for DEMO_B"');
        $screen->call('openApproval', $demoA)->assertHasNoErrors()->assertSet('approvals.'.$demoA, '');
    }

    public function test_a_decision_trims_the_entered_values_and_closes_its_form(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');
        $demoB = $this->orderIdOf('DEMO_B');
        $screen = $this->screen()
            ->call('openApproval', $demoA)->set('approvals.'.$demoA, ' 10.50 ')
            ->call('openRefusal', $demoB)->set('refusals.'.$demoB, '  ');

        $screen->call('approve', $demoA)->call('refuse', $demoB);

        $screen->assertSet('approvals', [])->assertSet('refusals', [])->assertHasNoErrors();
        $this->assertSame('10.50', $this->app->make('db')->table('trading_orders')->where('id', $demoA)->value('limit_price'));
        $this->assertSame('refused_by_operator', $this->statusOf($demoB));
        $audit = $this->app->make('db')->table('trading_audit_records')->where('entity_id', $demoB)->where('action', 'refused')->value('values');
        $this->assertArrayNotHasKey('note', json_decode((string) $audit, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_a_decision_on_an_order_without_an_open_form_changes_nothing(): void
    {
        $demoA = $this->orderIdOf('DEMO_A');

        $this->screen()->call('approve', $demoA)->call('refuse', $demoA);

        $this->assertSame('awaiting_approval', $this->statusOf($demoA));
    }

    /**
     * @return Testable<Component>
     */
    private function screen(): Testable
    {
        return Livewire::actingAs($this->operatorModel())->test(Orders::class);
    }

    private function statusOf(string $orderId): mixed
    {
        return $this->app->make('db')->table('trading_orders')->where('id', $orderId)->value('status');
    }
}
