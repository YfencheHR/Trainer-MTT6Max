<!DOCTYPE html>
<html lang="es" class="dark">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-config" content="{{ json_encode([
      'key' => config('broadcasting.connections.reverb.key'),
      'host' => config('services.reverb_client.host'),
      'port' => config('services.reverb_client.port'),
      'scheme' => config('services.reverb_client.scheme'),
    ]) }}">
    <title>Trainer MTT - 6 Max Poker</title>
    <link rel="icon" type="image/png" href="/images/poker-trainer-icon.png" />
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}" />
  </head>
  <body style="background-color: #12161c;" class="text-white min-h-screen">
    <x-navbar />
    @yield('content')
    <script type="module" src="{{ asset('assets/app.js') }}"></script>
  </body>
</html>
