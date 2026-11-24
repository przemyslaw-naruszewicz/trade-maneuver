<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><link rel="preconnect" href="https://fonts.bunny.net">@fonts @vite(['resources/css/app.css', 'resources/js/app.js'])<title>Sign in</title></head>
<body>
<form method="post" action="{{ route('login.store') }}">
    @csrf
    <input name="email" type="email">
    <input name="password" type="password">
    <button type="submit">Continue</button>
</form>
</body>
</html>
