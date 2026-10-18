<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Toko Mobil</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="page-shell @yield('page_class')">
        @if (session('success'))
            <div class="flash-message" role="status">{{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="flash-message flash-message--info" role="status">{{ session('info') }}</div>
        @endif
        @if ($errors->any())
            <div class="error-panel" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>