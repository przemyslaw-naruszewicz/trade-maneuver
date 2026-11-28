<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Trading\Operator\Support\LivewireComponentRoots;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\Feature\Trading\Operator\Support\RenderedPanelPages;
use Tests\Feature\Trading\Operator\Support\RequestForgeryPreventedInTests;
use Tests\Feature\Trading\Order\Support\OrderDecisions;
use Tests\Feature\Trading\Order\Support\OrdersAwaitingApproval;
use Tests\TestCase;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\RecordSignalOutcomeJob;

/**
 * The approve and refuse actions of the orders screen, sent as the browser sends them: as requests to the Livewire
 * update endpoint with the snapshot of the rendered screen.
 */
final class OrderDecisionActionsTest extends TestCase
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
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
    }

    public function test_the_approve_action_approves_the_order_as_the_logged_in_operator(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');

        $opened = $this->snapshotAfter($this->sendActions($this->screenSnapshot(), [], [['openApproval', $orderId]]));
        $this->sendActions($opened, ['approvals.'.$orderId => '10.50'], [['approve', $orderId]])->assertOk();

        $order = DB::table('trading_orders')->where('id', $orderId)->sole();
        $this->assertSame('approved', $order->status);
        $this->assertSame('10.50', $order->limit_price);
        $this->assertSame(95, $order->quantity);
        $this->assertDatabaseHas('trading_audit_records', ['entity_id' => $orderId, 'action' => 'approved', 'actor' => 'operator:'.$this->operatorModel()->id]);
    }

    public function test_the_refuse_action_refuses_the_order_as_the_logged_in_operator_with_the_note(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');

        $opened = $this->snapshotAfter($this->sendActions($this->screenSnapshot(), [], [['openRefusal', $orderId]]));
        $this->sendActions($opened, ['refusals.'.$orderId => 'The spread looks too wide today.'], [['refuse', $orderId]])->assertOk();

        $this->assertSame('refused_by_operator', DB::table('trading_orders')->where('id', $orderId)->value('status'));
        $audit = DB::table('trading_audit_records')->where('entity_id', $orderId)->where('action', 'refused')->sole();
        $this->assertSame('operator:'.$this->operatorModel()->id, $audit->actor);
        $this->assertSame('The spread looks too wide today.', json_decode((string) $audit->values, true, 512, JSON_THROW_ON_ERROR)['note']);
        $job = json_decode((string) DB::table('jobs')->sole()->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(RecordSignalOutcomeJob::class, $job['displayName']);
    }

    public function test_the_actions_refuse_a_request_without_an_authenticated_operator(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $snapshot = $this->screenSnapshot();
        $this->app->make('auth')->guard()->logout();

        $this->sendActions($snapshot, [], [['openApproval', $orderId], ['approve', $orderId]])->assertUnauthorized();
        $this->sendActions($snapshot, [], [['openRefusal', $orderId], ['refuse', $orderId]])->assertUnauthorized();

        $this->assertSame('awaiting_approval', DB::table('trading_orders')->where('id', $orderId)->value('status'));
        $this->assertSame(0, DB::table('trading_audit_records')->where('entity_id', $orderId)->whereIn('action', ['approved', 'refused'])->count());
    }

    public function test_the_actions_refuse_a_request_without_the_session_csrf_token(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $snapshot = $this->screenSnapshot();
        $this->app->bind(PreventRequestForgery::class, RequestForgeryPreventedInTests::class);

        $this->sendActions($snapshot, [], [['openApproval', $orderId], ['approve', $orderId]], token: null)->assertStatus(419);
        $this->sendActions($snapshot, [], [['openRefusal', $orderId], ['refuse', $orderId]], token: 'not-the-session-token')->assertStatus(419);
        $this->assertSame('awaiting_approval', DB::table('trading_orders')->where('id', $orderId)->value('status'));

        $this->sendActions($snapshot, [], [['openApproval', $orderId], ['approve', $orderId]])->assertOk();
        $this->assertSame('approved', DB::table('trading_orders')->where('id', $orderId)->value('status'));
    }

    public function test_the_approve_action_accepts_no_instrument_order_amount_or_quantity(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $snapshot = $this->screenSnapshot();

        $this->sendActions($snapshot, ['quantity' => 1, 'orderAmount' => '5000.00', 'instrument' => 'DEMO_X'], [['openApproval', $orderId]])->assertServerError();
        $this->sendActions($snapshot, [], [
            ['openApproval', $orderId],
            ['approve', $orderId, 'DEMO_X', '5000.00', 1],
        ])->assertOk();

        $order = DB::table('trading_orders')->where('id', $orderId)->sole();
        $this->assertSame('approved', $order->status);
        $this->assertSame('DEMO_A', $order->instrument_symbol);
        $this->assertSame('1000.00', $order->order_amount);
        $this->assertSame('10.20', $order->limit_price);
        $this->assertSame(98, $order->quantity);
    }

    private function screenSnapshot(): string
    {
        return (string) $this->componentRoot($this->panelPageAt('panel.orders'), 'trading.orders')->getAttribute('wire:snapshot');
    }

    /**
     * The snapshot an update response returns, which the browser sends with its next update.
     *
     * @param  TestResponse<Response>  $response
     */
    private function snapshotAfter(TestResponse $response): string
    {
        $response->assertOk();
        $snapshot = $response->json('components.0.snapshot');
        $this->assertIsString($snapshot);

        return $snapshot;
    }

    /**
     * Sends the property updates and the action calls of one update request of the orders screen, with the session
     * CSRF token unless another token, or none, is given.
     *
     * @param  array<string, mixed>  $updates
     * @param  list<non-empty-list<mixed>>  $calls  each the method name, then its parameters
     * @return TestResponse<Response>
     */
    private function sendActions(string $snapshot, array $updates, array $calls, ?string $token = ''): TestResponse
    {
        $payload = ['components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => array_map(static fn (array $call): array => ['path' => '', 'method' => $call[0], 'params' => array_slice($call, 1)], $calls),
        ]]];
        if ($token !== null) {
            $payload['_token'] = $token === '' ? session()->token() : $token;
        }

        return $this->withHeaders(['X-Livewire' => '1'])->postJson(app('livewire')->getUpdateUri(), $payload);
    }
}
