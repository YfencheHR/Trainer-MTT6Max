@extends('app')

@section('content')
<div class="container mx-auto px-4 py-8">
  <div class="mb-8">
    <h1 class="text-3xl font-bold mb-2" style="color: #d1d5db;">Equity Simulation</h1>
    <p style="color: #a1a5b0;">Motor de simulación Monte Carlo para análisis preflop de poker</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <p style="color: #a1a5b0;" class="text-sm">Python Service</p>
      <p class="text-2xl font-bold" style="color: #10b981;">✅ Online</p>
    </div>

    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <p style="color: #a1a5b0;" class="text-sm">Simulaciones Disponibles</p>
      <p class="text-2xl font-bold" style="color: #60a5fa;">10</p>
    </div>

    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <p style="color: #a1a5b0;" class="text-sm">Acciones</p>
      <a href="{{ route('equity.simulator') }}" class="text-sm px-2 py-1 rounded" style="background-color: #047857; color: white;">Simular →</a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <h3 class="text-lg font-bold mb-3" style="color: #10b981;">Simulator</h3>
      <p style="color: #a1a5b0;" class="text-sm mb-3">Interactive equity calculation with full control.</p>
      <a href="{{ route('equity.simulator') }}" class="text-emerald-500 hover:text-emerald-400">Launch →</a>
    </div>
    
    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <h3 class="text-lg font-bold mb-3" style="color: #60a5fa;">GTO Tables</h3>
      <p style="color: #a1a5b0;" class="text-sm mb-3">Pre-generated attack & defense ranges.</p>
      <p class="text-emerald-500">Coming soon</p>
    </div>
  </div>
</div>
@endsection
