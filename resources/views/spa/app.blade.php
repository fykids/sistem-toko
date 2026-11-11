<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sistem Toko Mobil</title>
    <script>window.spaUser = @js(auth()->user()?->only(['name', 'role', 'username']));</script>
    @vite('resources/js/spa.js')
    {{ Vite::fonts() }}
</head>
<body>
    <div id="app"></div>
</body>
</html>
