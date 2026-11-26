@php
    $message = match ($status) {
        401 => __('Рұқсат қажет'),
        403 => __('Бұл бетке қолжетімділігіңіз жоқ.'),
        404 => __('Бет табылмады.'),
        419 => __('Сеанс мерзімі аяқталды. Бетті жаңартып, қайталап көріңіз.'),
        429 => __('Сұраулар тым жиі жіберілді. Кейін қайталаңыз.'),
        503 => __('Қызмет уақытша қолжетімсіз.'),
        default => __('Жүйеде қате орын алды. Кейін қайталаңыз.'),
    };
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} · PedBase-KZ</title>
    <style>
        body { margin: 0; color: #101e39; font: 1rem system-ui, sans-serif; }
        main { max-width: 40rem; margin: 12vh auto 0; padding: 1.5rem; }
        h1 { overflow-wrap: anywhere; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
    <main>
        <p>PedBase-KZ · {{ $status }}</p>
        <h1>{{ $message }}</h1>
        <a href="{{ route('home') }}">{{ __('Басты бетке оралу') }}</a>
    </main>
</body>
</html>
