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
 * The Orders entry of the panel navigation, which every panel page shows.
 */
final class OrdersNavigationEntryTest extends TestCase
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

    public function test_the_orders_entry_follows_the_start_entry_and_links_to_the_orders_screen(): void
    {
        foreach (['panel.start', 'panel.orders'] as $page) {
            $entries = $this->navigationEntries($this->panelPageAt($page));

            $this->assertSame('Start', $this->labelOf($entries[0] ?? null), sprintf('On %s.', $page));
            $this->assertSame('Orders', $this->labelOf($entries[1] ?? null), sprintf('On %s.', $page));
            $this->assertSame(route('panel.orders'), $entries[1]->getAttribute('href'));
            $this->assertSame($page === 'panel.orders' ? 'page' : null, $entries[1]->getAttribute('aria-current'));
        }
    }

    public function test_the_orders_entry_shows_the_number_of_orders_awaiting_approval_on_every_panel_page(): void
    {
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
        $this->addOrder('DEMO_B', '2026-11-11T09:00:00Z');

        foreach (['panel.start', 'panel.orders'] as $page) {
            $entry = $this->navigationEntries($this->panelPageAt($page))[1];

            $this->assertSame('2', $this->text($entry, '.nav-count'), sprintf('On %s.', $page));
        }
    }

    /**
     * @return list<Element>
     */
    private function navigationEntries(HTMLDocument $page): array
    {
        $entries = [];
        foreach ($page->querySelectorAll('aside nav[aria-label="Panel"] > a') as $entry) {
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * The entry's own text, without the count it may show.
     */
    private function labelOf(?Element $entry): ?string
    {
        if ($entry === null) {
            return null;
        }

        return trim((string) $entry->firstChild?->textContent);
    }
}
