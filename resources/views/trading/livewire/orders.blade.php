<div wire:poll.30s data-live-updates>
    <header class="page-header flex flex-wrap items-end justify-between gap-6">
        {{-- As wide as the header text of the mockup, which also has a lede. --}}
        <div class="w-full max-w-2xl">
            <h1 class="page-title">Orders</h1>
        </div>
        <p class="live-status" data-live-status data-updated-at="{{ $updatedAt }} UTC">Live, updated {{ $updatedAt }} UTC</p>
    </header>

    <div class="notice notice-attention mt-8" role="status" data-connection-lost hidden>
        <p>Connection lost. Statuses and the time left may be out of date. Trying again.</p>
        <button class="btn btn-secondary" type="button" wire:click="$refresh">Retry now</button>
    </div>

    @if ($notice !== null)
        <div class="notice notice-attention mt-8" role="alert" data-decision-notice>
            <p>{{ $notice }}</p>
        </div>
    @endif

    <section class="mt-14" aria-labelledby="awaiting-approval">
        <h2 class="section-title" id="awaiting-approval">Awaiting approval<span class="section-count">{{ count($orders) === 0 ? '0' : count($orders).', soonest validity first' }}</span></h2>

        @forelse ($orders as $order)
            @php($approving = array_key_exists($order->id, $approvals))
            @php($refusing = array_key_exists($order->id, $refusals))
            <article class="order-band" wire:key="{{ $order->id }}" data-change-key="{{ $order->id }}" data-change-version="{{ $order->version }}" tabindex="0" aria-labelledby="order-{{ $order->id }}">
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
                    @if (! $approving && ! $refusing)
                        <div class="mt-6 flex flex-wrap gap-3" data-decision-actions>
                            <button class="btn btn-primary" type="button" wire:click="openApproval('{{ $order->id }}')">Approve</button>
                            <button class="btn btn-secondary" type="button" wire:click="openRefusal('{{ $order->id }}')">Refuse</button>
                        </div>
                    @endif
                </div>
                @if ($approving)
                    @php($fieldId = 'limit-'.$order->id)
                    @php($errorKey = 'approvals.'.$order->id)
                    <form class="action-panel" wire:key="approve-{{ $order->id }}" wire:submit="approve('{{ $order->id }}')" aria-label="Approve the order for {{ $order->instrumentSymbol }}">
                        @error($errorKey)
                            <div class="max-w-sm" data-limit-price-field>
                                <label class="field-label" for="{{ $fieldId }}">New limit price, optional</label>
                                <div class="input-addon"><input class="input input-invalid" id="{{ $fieldId }}" inputmode="decimal" wire:model="approvals.{{ $order->id }}" aria-invalid="true" aria-describedby="{{ $fieldId }}-error"><span class="note">{{ $order->currency }}</span></div>
                                <p class="field-error" id="{{ $fieldId }}-error">{{ $message }}</p>
                            </div>
                        @else
                            {{-- The field keeps the width it has in the mockup beside a preview of the recalculated quantity and cost, which is not built. --}}
                            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(14rem,100%),1fr))] gap-6">
                                <div data-limit-price-field>
                                    <label class="field-label" for="{{ $fieldId }}">New limit price, optional</label>
                                    <div class="input-addon"><input class="input" id="{{ $fieldId }}" inputmode="decimal" wire:model="approvals.{{ $order->id }}"><span class="note">{{ $order->currency }}</span></div>
                                    <p class="field-hint">Leave empty to keep {{ $order->limitPrice }}.</p>
                                </div>
                                <div aria-hidden="true"></div>
                                <div aria-hidden="true"></div>
                            </div>
                        @enderror
                        <div class="flex flex-wrap items-center gap-4" data-form-actions>
                            <button class="btn btn-primary" type="submit">Approve order</button>
                            <button class="btn btn-quiet" type="button" wire:click="close('{{ $order->id }}')">Close</button>
                        </div>
                    </form>
                @elseif ($refusing)
                    @php($fieldId = 'note-'.$order->id)
                    <form class="action-panel" wire:key="refuse-{{ $order->id }}" wire:submit="refuse('{{ $order->id }}')" aria-label="Refuse the order for {{ $order->instrumentSymbol }}">
                        <div class="max-w-2xl" data-note-field>
                            <label class="field-label" for="{{ $fieldId }}">Note, optional</label>
                            <textarea class="input py-2" id="{{ $fieldId }}" rows="3" wire:model="refusals.{{ $order->id }}"></textarea>
                            <p class="field-hint">The note stays in the audit trail. The analytics application receives only the decline reason.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-4" data-form-actions>
                            <button class="btn btn-primary" type="submit">Refuse order</button>
                            <button class="btn btn-quiet" type="button" wire:click="close('{{ $order->id }}')">Close</button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <p class="mt-8 font-display text-4xl tracking-tight">No orders await approval.</p>
            <p class="mt-3 max-w-md text-sm text-ink-muted">An order appears here after a trade signal passes evaluation.</p>
        @endforelse
    </section>

    <section class="mt-16" aria-labelledby="non-terminal-orders">
        <h2 class="section-title" id="non-terminal-orders">Non-terminal orders<span class="section-count">{{ count($otherNonTerminal) }}</span></h2>
        @if ($otherNonTerminal === [])
            <p class="mt-6 text-sm text-ink-muted">No other non-terminal orders.</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr><th scope="col">Instrument</th><th scope="col">Status</th><th scope="col">Execution progress</th><th scope="col">Limit price</th><th scope="col">Valid until</th><th scope="col"><span class="sr-only">Action</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($otherNonTerminal as $order)
                            <tr wire:key="{{ $order->id }}" data-change-key="{{ $order->id }}" data-change-version="{{ $order->version }}">
                                <td class="font-medium">{{ $order->instrumentSymbol }}<span class="note block font-normal">{{ $order->instrumentMic }}</span></td>
                                <td><span class="status {{ $order->statusClass }}">{{ $order->status }}</span></td>
                                <td>{{ $order->executionProgress }}<span class="meter" aria-hidden="true"></span></td>
                                <td>{{ $order->limitPrice }}</td>
                                <td>{{ $order->validUntil }}</td>
                                {{-- The column of the mockup's cancel action, which order cancellation adds. --}}
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-16" aria-labelledby="terminal-orders">
        <h2 class="section-title" id="terminal-orders">Terminal orders<span class="section-count">{{ $terminalPage->total() === 0 ? '0' : $terminalPage->total().', most recent first' }}</span></h2>
        @if ($terminal === [])
            <p class="mt-6 text-sm text-ink-muted">No terminal orders.</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr><th scope="col">Instrument</th><th scope="col">Status</th><th scope="col">Execution progress</th><th scope="col">Limit price</th><th scope="col">Ended</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($terminal as $order)
                            <tr wire:key="{{ $order->id }}" data-change-key="{{ $order->id }}" data-change-version="{{ $order->version }}">
                                <td class="font-medium">{{ $order->instrumentSymbol }}<span class="note block font-normal">{{ $order->instrumentMic }}</span></td>
                                <td><span class="status {{ $order->statusClass }}">{{ $order->status }}</span></td>
                                <td>{{ $order->executionProgress }}<span class="meter" aria-hidden="true"></span></td>
                                <td>{{ $order->limitPrice }}</td>
                                <td>{{ $order->ended }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <nav class="mt-6 flex flex-wrap items-center justify-between gap-4" aria-label="Terminal orders pages">
                <p class="note">{{ $terminalPage->firstItem() }} to {{ $terminalPage->lastItem() }} of {{ $terminalPage->total() }}</p>
                <div class="flex gap-3">
                    <button class="btn btn-secondary" type="button" wire:click="previousPage('{{ $terminalPageName }}')" @disabled($terminalPage->onFirstPage())>Previous</button>
                    <button class="btn btn-secondary" type="button" wire:click="nextPage('{{ $terminalPageName }}')" @disabled(! $terminalPage->hasMorePages())>Next</button>
                </div>
            </nav>
        @endif
    </section>
</div>
