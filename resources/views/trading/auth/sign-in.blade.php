<x-trading::layouts.sign-in title="Sign in">
    <form class="w-full max-w-sm" method="post" action="{{ route('login.store') }}">
        @csrf
        <h1 class="font-display text-5xl tracking-tight">Sign in</h1>
        <div class="mt-10 grid gap-6">
            <x-trading::text-field name="email" label="E-mail address" type="email" autocomplete="username" :value="old('email')" required autofocus />
            <x-trading::text-field name="password" label="Password" type="password" autocomplete="current-password" required />
            <button class="btn btn-primary w-full" type="submit">Continue</button>
        </div>
    </form>
</x-trading::layouts.sign-in>
