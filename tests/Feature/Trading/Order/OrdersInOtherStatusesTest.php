<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\LivewireComponentRoots;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\Feature\Trading\Operator\Support\RenderedPanelPages;
use Tests\Feature\Trading\Order\Support\OrderDecisions;
use Tests\Feature\Trading\Order\Support\OrdersAwaitingApproval;
use Tests\TestCase;

/**
 * The orders screen and the start page with orders that no longer await approval.
 */
final class OrdersInOtherStatusesTest extends TestCase
{
    use LivewireComponentRoots;
    use OperatorAccounts;
    use OrderDecisions;
    use OrdersAwaitingApproval;
    use RefreshDatabase;
    use RenderedPanelPages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09T11:00:00Z'));
        $this->createEnrolledOperator();
        $this->addTheInvestmentConfiguration();
    }

    public function test_an_approved_order_is_listed_with_its_status_and_no_execution_progress_and_the_start_page_counts_it(): void
    {
        $this->addOrder('DEMO_A', '2026-11-10T17:00:00Z', referencePrice: '10.00');
        $this->addOrder('DEMO_B', '2026-11-11T09:00:00Z');
        // 1000.00 EUR at 11.05 EUR buys 90 units.
        $this->approveOrderOf('DEMO_A', '11.05');

        $screen = $this->panelPageAt('panel.orders');

        $this->assertSame(['DEMO_B'], $this->awaitingApprovalSymbols($screen));
        $this->assertSame('Non-terminal orders 1', $this->spacedText($screen, '#non-terminal-orders'));
        $this->assertSame([
            ['DEMO_A DEMO', 'Approved', 'None, 0 of 90', '11.05 EUR', '10 Nov, 17:00 UTC'],
        ], $this->rowsOf($screen, '#non-terminal-orders + * table'));
        $status = $screen->querySelector('#non-terminal-orders + * tbody .status');
        $this->assertNotNull($status);
        $this->assertSame('status status-approved', $status->getAttribute('class'));

        $start = $this->panelPageAt('panel.start');
        $this->assertSame('1', $this->text($this->tile($start, 'Other non-terminal orders'), '.tile-value'));
        $this->assertSame('1', $this->text($this->tile($start, 'Awaiting approval'), '.tile-value'));
    }

    /**
     * @return list<string>
     */
    private function awaitingApprovalSymbols(HTMLDocument $screen): array
    {
        $symbols = [];
        foreach ($screen->querySelectorAll('article.order-band .order-symbol') as $symbol) {
            $symbols[] = $this->collapsed((string) $symbol->textContent);
        }

        return $symbols;
    }

    /**
     * The text of each cell of each body row of a table, with its whitespace collapsed.
     *
     * @return list<list<string>>
     */
    private function rowsOf(HTMLDocument $screen, string $tableSelector): array
    {
        $table = $screen->querySelector($tableSelector);
        $this->assertNotNull($table, sprintf('No table matches %s.', $tableSelector));
        $rows = [];
        foreach ($table->querySelectorAll('tbody tr') as $row) {
            $cells = [];
            foreach ($row->querySelectorAll('td') as $cell) {
                $cells[] = $this->textOfChildren($cell);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * The text of an element, with a space between the texts of its children, such as a heading and its count.
     */
    private function spacedText(HTMLDocument $screen, string $selector): string
    {
        $element = $screen->querySelector($selector);
        $this->assertNotNull($element, sprintf('No element matches %s.', $selector));

        return $this->textOfChildren($element);
    }

    private function textOfChildren(Element $element): string
    {
        $texts = [];
        foreach ($element->childNodes as $child) {
            $texts[] = (string) $child->textContent;
        }

        return $this->collapsed(implode(' ', $texts));
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
