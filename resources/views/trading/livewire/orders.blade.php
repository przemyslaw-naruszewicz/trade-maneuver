<div>
    <header class="page-header flex flex-wrap items-end justify-between gap-6">
        <div>
            <h1 class="page-title">Orders</h1>
        </div>
    </header>

    <section class="mt-14" aria-labelledby="awaiting-approval">
        <h2 class="section-title" id="awaiting-approval">Awaiting approval<span class="section-count">{{ count($orders) }}, soonest validity first</span></h2>

        @foreach ($orders as $order)
            <article class="order-band" wire:key="{{ $order->id }}" aria-labelledby="order-{{ $order->id }}">
                <div>
                    <h3 class="order-symbol" id="order-{{ $order->id }}">{{ $order->instrumentSymbol }}</h3>
                    <p class="note mt-1">{{ $order->listing }}</p>
                    <dl @class(['decision-pair mt-5', 'decision-pair-soonest' => $loop->first])>
                        <div class="decision-cell"><dt class="caption">Status</dt><dd class="status status-awaiting-approval">{{ $order->status }}</dd></div>
                        <div class="decision-cell"><dt class="caption">Signal validity</dt><dd class="time-left" data-time-left-until="{{ $order->validUntilIso }}"></dd></div>
                    </dl>
                    <p class="note mt-2">Valid until <time datetime="{{ $order->validUntilIso }}">{{ $order->validUntil }}</time></p>
                </div>
                <div>
                    <p class="column-head">Trade signal</p>
                    <dl class="facts mt-4">
                        <div><dt class="caption">Confidence</dt><dd class="fact-value">{{ $order->confidence }}</dd></div>
                        <div><dt class="caption">Horizon</dt><dd class="fact-value">{{ $order->horizon }}</dd></div>
                        <div><dt class="caption">Expected move</dt>
                            @if ($order->expectedMove === null)
                                <dd class="fact-value text-ink-muted">Not provided</dd>
                            @else
                                <dd class="fact-value">{{ $order->expectedMove }}</dd>
                            @endif
                        </div>
                        <div><dt class="caption">Reference price</dt><dd class="fact-value">{{ $order->referencePrice }}</dd><dd class="note mt-1">{{ $order->referencePriceAsOf }}</dd></div>
                        <div><dt class="caption">Analytics application</dt><dd class="fact-value font-mono">{{ $order->analyticsApplication }}</dd></div>
                    </dl>
                </div>
                <div>
                    <p class="column-head">Order</p>
                    <dl class="facts mt-4">
                        <div><dt class="caption">Limit price</dt><dd class="fact-value-lg">{{ $order->limitPrice }}</dd></div>
                        <div><dt class="caption">Quantity</dt><dd class="fact-value-lg">{{ $order->quantity }}</dd></div>
                        <div><dt class="caption">Order amount</dt><dd class="fact-value">{{ $order->orderAmount }}</dd></div>
                    </dl>
                </div>
            </article>
        @endforeach
    </section>
</div>
