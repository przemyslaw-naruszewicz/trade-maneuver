@props(['title'])
<x-trading::document :title="$title">
    <div class="flex grow flex-wrap">
        <section class="flex grow basis-[32rem] flex-col justify-between gap-12 bg-sunken p-6 text-ink lg:p-12" aria-label="Trade Maneuver">
            <p class="flex items-center gap-3 text-xs font-medium"><x-trading::simulated-broker-label /></p>
            <div>
                <p class="inline-grid gap-3 font-display text-6xl leading-none tracking-tight lg:text-7xl"><span>Trade</span><span class="block h-0.5 bg-accent-mark"></span><span>Maneuver</span></p>
                <p class="mt-6 max-w-md text-sm text-pretty text-ink-muted">Trade Maneuver is a simulation, built as a portfolio and educational project. It has no connection to a real market, real funds, or a real brokerage account.</p>
            </div>
        </section>
        <main class="flex grow basis-[24rem] items-center justify-center p-6 lg:p-12">
            {{ $slot }}
        </main>
    </div>
</x-trading::document>
