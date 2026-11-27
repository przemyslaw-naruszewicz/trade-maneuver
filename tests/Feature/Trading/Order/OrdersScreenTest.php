<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;

/**
 * The orders screen of the panel, rendered for the logged-in operator against the test database.
 */
final class OrdersScreenTest extends TestCase
{
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
