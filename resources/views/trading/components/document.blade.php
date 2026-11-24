@props(['title'])
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Trade Maneuver</title>
    {{-- Applies the theme stored by the theme switch before the first paint, so the other theme never flashes. --}}
    <script>
        try {
            const storedTheme = localStorage.getItem('trade-maneuver-theme');
            if (storedTheme === 'light' || storedTheme === 'dark') {
                document.documentElement.dataset.theme = storedTheme;
            }
        } catch {}
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper font-sans text-ink antialiased">
<div class="flex min-h-screen flex-col">
    {{ $slot }}
</div>
</body>
</html>
