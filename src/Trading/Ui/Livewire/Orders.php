<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommandHandler;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;
use TradeManeuver\Trading\Application\Order\Exception\OrderAlreadyDecidedException;
use TradeManeuver\Trading\Domain\Order\Exception\ApprovalLeavesZeroQuantityException;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\Exception\SignalValidityEndedException;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderOverviewReadModel;

/**
 * The orders screen: the orders awaiting approval with their approve and refuse forms, then the other non-terminal
 * orders and the terminal ones. The open forms and what the operator typed in them are the component's state, so an
 * update keeps them while the lists around them change.
 */
#[Layout('trading.components.layouts.panel')]
#[Title('Orders')]
final class Orders extends Component
{
    use WithPagination;

    private const int TERMINAL_ORDERS_PER_PAGE = 10;

    private const string TERMINAL_ORDERS_PAGE = 'terminal';

    public const string ALREADY_DECIDED = 'This order was already decided in another tab, so nothing changed. The list below shows its current status.';

    public const string SIGNAL_VALIDITY_ENDED = 'The signal validity of this order has ended, so it can no longer be approved. Nothing changed.';

    public const string ZERO_QUANTITY = 'This limit price leaves a quantity of zero, so the order stays awaiting approval. Enter a lower limit price.';

    public const string INVALID_LIMIT_PRICE = 'Enter a limit price greater than zero in the order currency, with at most two decimal places.';

    /** @var array<string, string> the new limit price typed in each open approve form, by order */
    public array $approvals = [];

    /** @var array<string, string> the note typed in each open refuse form, by order */
    public array $refusals = [];

    /** The message about the last decision that changed nothing, if any. */
    public ?string $notice = null;

    public function openApproval(string $orderId): void
    {
        unset($this->refusals[$orderId]);
        $this->approvals[$orderId] = '';
        $this->notice = null;
    }

    public function openRefusal(string $orderId): void
    {
        unset($this->approvals[$orderId]);
        $this->refusals[$orderId] = '';
        $this->notice = null;
    }

    public function close(string $orderId): void
    {
        unset($this->approvals[$orderId], $this->refusals[$orderId]);
        $this->resetErrorBag('approvals.'.$orderId);
    }

    public function approve(string $orderId, ApproveOrderCommandHandler $approveOrder): void
    {
        if (! array_key_exists($orderId, $this->approvals)) {
            return;
        }
        $this->resetErrorBag('approvals.'.$orderId);
        $newLimitPrice = trim($this->approvals[$orderId]);

        try {
            $approveOrder->handle(new ApproveOrderCommand(
                $orderId,
                $this->operatorId(),
                $newLimitPrice === '' ? null : $newLimitPrice,
                $newLimitPrice === '' ? null : OrderOverviewReadModel::query()->find($orderId)?->currency,
            ));
        } catch (ApprovalLeavesZeroQuantityException) {
            $this->addError('approvals.'.$orderId, self::ZERO_QUANTITY);

            return;
        } catch (InvalidOrderValueException) {
            $this->addError('approvals.'.$orderId, self::INVALID_LIMIT_PRICE);

            return;
        } catch (SignalValidityEndedException) {
            $this->notice = self::SIGNAL_VALIDITY_ENDED;
        } catch (OrderAlreadyDecidedException) {
            $this->notice = self::ALREADY_DECIDED;
        }
        $this->close($orderId);
    }

    public function refuse(string $orderId, RefuseOrderCommandHandler $refuseOrder): void
    {
        if (! array_key_exists($orderId, $this->refusals)) {
            return;
        }
        $note = trim($this->refusals[$orderId]);

        try {
            $refuseOrder->handle(new RefuseOrderCommand($orderId, $this->operatorId(), $note === '' ? null : $note));
        } catch (OrderAlreadyDecidedException) {
            $this->notice = self::ALREADY_DECIDED;
        }
        $this->close($orderId);
    }

    public function render(ClockInterface $clock): View
    {
        // The time of this render is the time of the last successful update that the browser shows.
        $updatedAt = $clock->now()->setTimezone(new DateTimeZone('UTC'))->format('H:i:s');
        $awaitingApproval = [];
        foreach (OrderOverviewReadModel::awaitingApproval()->get() as $order) {
            $awaitingApproval[] = OrderAwaitingApprovalPresentation::of($order);
        }
        $otherNonTerminal = [];
        foreach (OrderOverviewReadModel::otherNonTerminal()->get() as $order) {
            $otherNonTerminal[] = OrderRowPresentation::of($order);
        }
        $terminalPage = OrderOverviewReadModel::terminal()->paginate(self::TERMINAL_ORDERS_PER_PAGE, pageName: self::TERMINAL_ORDERS_PAGE);
        $terminal = [];
        foreach ($terminalPage->items() as $order) {
            $terminal[] = OrderRowPresentation::of($order);
        }

        return view('trading.livewire.orders', [
            'orders' => $awaitingApproval,
            'otherNonTerminal' => $otherNonTerminal,
            'terminal' => $terminal,
            'terminalPage' => $terminalPage,
            'terminalPageName' => self::TERMINAL_ORDERS_PAGE,
            'updatedAt' => $updatedAt,
        ]);
    }

    private function operatorId(): string
    {
        return (string) auth()->id();
    }
}
