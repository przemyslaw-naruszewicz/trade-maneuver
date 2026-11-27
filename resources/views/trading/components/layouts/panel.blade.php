{{-- The layout of every panel page. Later screens add their navigation entries in the mockup's order. --}}
<x-trading::document :title="$title ?? 'Panel'">
    <p class="sim-band"><x-trading::simulated-broker-label /></p>
    <div class="flex grow flex-wrap">
        <aside class="shell-aside">
            <a class="brand" href="{{ route('panel.start') }}" aria-label="Trade Maneuver">
                <span class="brand-name"><span>Trade</span><span class="brand-rule"></span><span>Maneuver</span></span>
            </a>
            <nav class="flex flex-col" aria-label="Panel">
                <a class="nav-link" href="{{ route('panel.start') }}" @if (request()->routeIs('panel.start')) aria-current="page" @endif>Start</a>
                <a class="nav-link" href="{{ route('panel.orders') }}" @if (request()->routeIs('panel.orders')) aria-current="page" @endif>Orders</a>
            </nav>
            <div class="mt-auto flex flex-col items-start gap-1">
                <button class="btn btn-quiet" type="button" data-theme-toggle>Switch theme</button>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-quiet" type="submit">Log out</button>
                </form>
            </div>
        </aside>
        <main class="shell-main">
            {{ $slot }}
        </main>
    </div>
</x-trading::document>
