<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Trading\Operator\Support\LivewireComponentRoots;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;
use TradeManeuver\Trading\Ui\Livewire\Orders;

/**
 * The orders screen of the panel, rendered for the logged-in operator against the test database.
 */
final class OrdersScreenTest extends TestCase
{
    use LivewireComponentRoots;
    use OperatorAccounts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfigurationBuilder::anInvestmentConfiguration()->create());
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

    /**
     * Adds the order awaiting approval of a new long trade signal on the instrument, with the reference price
     * 10.00 EUR, under the settings of the order builder.
     */
    private function addOrder(string $symbol, string $validUntil, string $referencePrice = '10.00'): void
    {
        $tradeSignals = $this->app->make(TradeSignalRepositoryInterface::class);
        $tradeSignal = TradeSignal::receive(
            new TradeSignalId(Str::uuid7()->toString()),
            new AnalyticsApplicationId('demo-analytics'),
            new TradeSignalContent(
                UtcTimestamp::fromString('2026-11-07T09:00:00Z'),
                new InvestmentConfigurationRef('demo-investment'),
                new Instrument($symbol, new Mic('DEMO')),
                Direction::Long,
                Confidence::of('0.85'),
                Horizon::of(5, null),
                null,
                ReferencePrice::of($referencePrice, 'EUR', '2026-11-07T08:59:00Z'),
                UtcTimestamp::fromString($validUntil),
            ),
            new DateTimeImmutable(TradeSignalBuilder::RECEIVED_AT),
        );
        $tradeSignals->add($tradeSignal);
        $order = $tradeSignal->createOrder(
            new OrderId(Str::uuid7()->toString()),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings(),
            $tradeSignals->nextOutcomeIdentity(),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );
        $this->assertInstanceOf(Order::class, $order);
        $this->app->make(OrderRepositoryInterface::class)->add($order);
    }

    private function ordersScreen(): HTMLDocument
    {
        $html = (string) $this->actingAs($this->operatorModel())->get(route('panel.orders'))->assertOk()->getContent();

        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
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

    private function text(Element $element, string $selector): string
    {
        $found = $element->querySelector($selector);
        $this->assertNotNull($found, sprintf('No element matches %s.', $selector));

        return $this->collapsed((string) $found->textContent);
    }

    private function collapsed(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
