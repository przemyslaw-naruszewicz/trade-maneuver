<div>
    <header class="page-header flex flex-wrap items-end justify-between gap-6">
        <div>
            <h1 class="page-title">Start</h1>
            <p class="page-lede">In manual execution mode, every order waits for your approval before it reaches the simulated broker.</p>
        </div>
    </header>

    {{-- The frame lines of the tiles are elements of their own; the free cell is kept for the tiles of later screens. --}}
    <div class="tiles mt-12">
        <span class="tile-rule col-span-full row-start-2" aria-hidden="true"></span>
        <span class="tile-rule col-span-full row-start-4" aria-hidden="true"></span>
        <span class="tile-rule col-span-full row-start-6" aria-hidden="true"></span>
        <span class="tile-rule col-start-2 row-span-full" aria-hidden="true"></span>
        <span class="tile-rule col-start-4 row-span-full" aria-hidden="true"></span>
        <span class="tile-rule col-start-6 row-span-full" aria-hidden="true"></span>
        <dl class="tile sm:col-start-3 sm:row-start-3">
            <dt class="tile-label">Awaiting approval</dt>
            <dd class="tile-note">Orders await your decision</dd>
            <dd class="tile-value tile-value-accent">{{ $awaitingApproval }}</dd>
        </dl>
        <dl class="tile sm:col-start-5 sm:row-start-3">
            <dt class="tile-label">Soonest signal validity</dt>
            @if ($soonest !== null)
                <dd class="tile-note">{{ $soonest->instrumentSymbol }}, valid until <time datetime="{{ $soonest->validUntilIso }}">{{ $soonest->validUntil }}</time></dd>
                <dd class="tile-value" data-time-left-until="{{ $soonest->validUntilIso }}" data-time-left-unit-class="tile-unit"></dd>
            @endif
        </dl>
        <a class="tile tile-link sm:col-start-5 sm:row-start-5" href="{{ route('panel.orders') }}">
            <span class="tile-label">Orders</span>
            <span class="tile-action">
                <span class="tile-action-label">Open orders</span>
                <span class="tile-door"><svg class="size-7 fill-none stroke-current stroke-[1.5]" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12H20M14 6l6 6-6 6"></path></svg></span>
            </span>
        </a>
    </div>
</div>
