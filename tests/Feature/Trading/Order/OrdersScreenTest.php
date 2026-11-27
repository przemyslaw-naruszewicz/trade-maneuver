<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Trading\Operator\Support\LivewireComponentRoots;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\Feature\Trading\Operator\Support\RenderedPanelPages;
use Tests\Feature\Trading\Order\Support\OrdersAwaitingApproval;
use Tests\TestCase;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Ui\Livewire\Orders;

/**
 * The orders screen of the panel, rendered for the logged-in operator against the test database.
 */
final class OrdersScreenTest extends TestCase
{
    use LivewireComponentRoots;
    use OperatorAccounts;
    use OrdersAwaitingApproval;
    use RefreshDatabase;
    use RenderedPanelPages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
        $this->addTheInvestmentConfiguration();
    }

    public function test_the_operator_sees_an_order_awaiting_approval_with_the_fields_of_its_trade_signal_and_of_the_order(): void
    {
        $this->app->make(TradeSignalRepositoryInterface::class)->add(TradeSignalBuilder::received(referencePrice: '10.00'));
        $this->app->make(OrderRepositoryInterface::class)->add(OrderBuilder::awaitingApproval());

        $order = $this->orderOf($this->ordersScreen(), 'DEMO_A');

        $this->assertSame('MIC DEMO, long', $this->text($order, '.order-symbol + .note'));
        $this->assertSame([
            'Status' => ['Awaiting approval'],
            'Signal validity' => [''],
            'Confidence' => ['0.85'],
            'Horizon' => ['5 trading sessions, until 20 Nov 2026'],
            'Expected move' => ['+1.5 % to +4.0 %'],
            'Reference price' => ['10.00 EUR', 'As of 7 Nov 2026, 08:59 UTC'],
            'Analytics application' => ['demo-analytics'],
            'Limit price' => ['10.20 EUR'],
            'Quantity' => ['98'],
            'Order amount' => ['1000.00 EUR'],
        ], $this->fieldsOf($order));
        $this->assertSame('Valid until 10 Nov 2026, 16:00 UTC', $this->text($order, '.decision-pair + .note'));
    }

    public function test_the_orders_awaiting_approval_are_sorted_by_signal_validity_with_the_soonest_first(): void
    {
        $this->addOrder('DEMO_B', '2026-11-11T09:00:00Z');
        $this->addOrder('DEMO_C', '2026-11-12T09:00:00Z');
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');

        $symbols = [];
        foreach ($this->ordersScreen()->querySelectorAll('article.order-band') as $order) {
            $symbols[] = $this->text($order, '.order-symbol');
        }

        $this->assertSame(['DEMO_A', 'DEMO_B', 'DEMO_C'], $symbols);
    }

    public function test_decimal_values_are_displayed_exactly(): void
    {
        // Through float, this reference price would be displayed as 0.1.
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z', referencePrice: '0.10000000000000001');

        $fields = $this->fieldsOf($this->orderOf($this->ordersScreen(), 'DEMO_A'));

        $this->assertSame('0.10000000000000001 EUR', $fields['Reference price'][0]);
        $this->assertSame(['0.10 EUR'], $fields['Limit price']);
        $this->assertSame(['10000'], $fields['Quantity']);
    }

    public function test_after_a_new_order_the_next_render_shows_it_and_a_new_last_update_time(): void
    {
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
        $this->travelTo(CarbonImmutable::parse('2026-11-09T10:00:00.400Z'));
        $screen = Livewire::test(Orders::class)
            ->assertSeeText('Live, updated 10:00:00 UTC')
            ->assertDontSeeText('DEMO_B');

        $this->travelTo(CarbonImmutable::parse('2026-11-09T10:00:31Z'));
        $this->addOrder('DEMO_B', '2026-11-11T09:00:00Z');
        $screen->call('$refresh');

        $screen->assertSeeText('Live, updated 10:00:31 UTC')->assertSeeTextInOrder(['DEMO_A', 'DEMO_B']);
    }

    public function test_the_rendered_orders_screen_polls_every_30_seconds(): void
    {
        $root = $this->componentRoot($this->ordersScreen(), 'trading.orders');

        $this->assertTrue($root->hasAttribute('wire:poll.30s'), 'The orders screen was expected to poll every 30 seconds.');
    }

    public function test_the_orders_screen_says_so_when_no_order_awaits_approval(): void
    {
        $screen = $this->ordersScreen();

        $this->assertNull($screen->querySelector('article.order-band'));
        $this->assertSame('Awaiting approval 0', $this->text($screen, '#awaiting-approval'));
        $this->assertSame('No orders await approval.', $this->text($screen, '#awaiting-approval + p'));
    }

    public function test_an_unauthenticated_request_cannot_see_the_orders_screen(): void
    {
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');

        $this->get(route('panel.orders'))->assertRedirect(route('login'))->assertDontSeeText('DEMO_A');
    }

    public function test_a_request_that_passed_only_the_password_step_cannot_see_the_orders_screen(): void
    {
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
        $this->submitPassword()->assertRedirect(route('login.totp'));

        $this->get(route('panel.orders'))->assertRedirect(route('login'))->assertDontSeeText('DEMO_A');
        $this->assertGuest();
    }

    private function ordersScreen(): HTMLDocument
    {
        return $this->panelPageAt('panel.orders');
    }

    private function orderOf(HTMLDocument $screen, string $symbol): Element
    {
        foreach ($screen->querySelectorAll('article.order-band') as $order) {
            if ($this->text($order, '.order-symbol') === $symbol) {
                return $order;
            }
        }
        $this->fail(sprintf('The orders screen shows no order of %s.', $symbol));
    }

    /**
     * The values of each field of an order, by caption, with their whitespace collapsed.
     *
     * @return array<string, list<string>>
     */
    private function fieldsOf(Element $order): array
    {
        $fields = [];
        foreach ($order->querySelectorAll('dl > div') as $field) {
            $caption = $this->text($field, 'dt');
            foreach ($field->querySelectorAll('dd') as $value) {
                $fields[$caption][] = $this->collapsed((string) $value->textContent);
            }
        }

        return $fields;
    }
}
