<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\Feature\Trading\Operator\Support\RenderedPanelPages;
use Tests\Feature\Trading\Order\Support\OrdersAwaitingApproval;
use Tests\TestCase;

/**
 * The start page of the panel and what it shows of the orders awaiting approval.
 */
final class StartPageTest extends TestCase
{
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

    public function test_the_start_page_shows_the_number_of_orders_awaiting_approval_with_a_link_to_the_orders_screen(): void
    {
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
        $this->addOrder('DEMO_B', '2026-11-11T09:00:00Z');

        $page = $this->startPage();

        $this->assertSame('2', $this->text($this->tile($page, 'Awaiting approval'), '.tile-value'));
        $link = $this->tile($page, 'Orders');
        $this->assertSame('a', $link->localName);
        $this->assertSame(route('panel.orders'), $link->getAttribute('href'));
    }

    public function test_the_start_page_shows_the_soonest_signal_validity_with_its_instrument(): void
    {
        $this->addOrder('DEMO_B', '2026-11-11T09:00:00Z');
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');

        $tile = $this->tile($this->startPage(), 'Soonest signal validity');

        $this->assertSame('DEMO_A, valid until 10 Nov 2026, 16:00 UTC', $this->text($tile, '.tile-note'));
        $timeLeft = $tile->querySelector('.tile-value');
        $this->assertNotNull($timeLeft);
        $this->assertSame('2026-11-10T16:00:00+00:00', $timeLeft->getAttribute('data-time-left-until'));
    }

    private function startPage(): HTMLDocument
    {
        return $this->panelPageAt('panel.start');
    }

    private function tile(HTMLDocument $page, string $label): Element
    {
        foreach ($page->querySelectorAll('.tile') as $tile) {
            if ($this->text($tile, '.tile-label') === $label) {
                return $tile;
            }
        }
        $this->fail(sprintf('The start page shows no tile %s.', $label));
    }
}
