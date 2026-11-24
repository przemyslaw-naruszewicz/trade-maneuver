<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>TOTP challenge</title></head>
<body>
<form method="post" action="{{ route('login.totp.store') }}">
    @csrf
    <input name="code" inputmode="numeric">
    <button type="submit">Sign in</button>
</form>
</body>
</html>
