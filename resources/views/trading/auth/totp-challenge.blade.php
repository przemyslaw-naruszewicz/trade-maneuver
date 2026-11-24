<x-trading::layouts.sign-in title="Enter your code">
    <form class="w-full max-w-sm" method="post" action="{{ route('login.totp.store') }}">
        @csrf
        <h1 class="font-display text-5xl tracking-tight">Enter your code</h1>
        <div class="mt-10 grid gap-6">
            <x-trading::text-field
                name="code"
                label="Authentication code"
                hint="Enter the 6 digits from your authenticator app."
                class="text-center font-mono text-2xl tracking-[0.4em]"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                required
                autofocus
            />
            <button class="btn btn-primary w-full" type="submit">Sign in</button>
            <a class="btn btn-quiet" href="{{ route('login') }}">Back</a>
        </div>
    </form>
</x-trading::layouts.sign-in>
