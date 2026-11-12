@props([
    'pot' => 0,
    'toCall' => 0,
    'heroStack' => 0
])

<div style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.75rem; padding: 1.5rem;">

  <!-- Información de la Apuesta -->
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
    <div>
      <p style="color: #9ca3af; font-size: 0.875rem; margin: 0 0 0.5rem 0; font-weight: 600;">BOTE ACTUAL</p>
      <p style="color: #10b981; font-size: 1.375rem; font-weight: bold; margin: 0;">{{ $pot }} bb</p>
    </div>

    <div>
      <p style="color: #9ca3af; font-size: 0.875rem; margin: 0 0 0.5rem 0; font-weight: 600;">A PAGAR</p>
      <p style="color: #60a5fa; font-size: 1.375rem; font-weight: bold; margin: 0;">{{ $toCall }} bb</p>
    </div>
  </div>

  <!-- Barra de Apuesta -->
  <div style="border-top: 1px solid #2e3a48; padding-top: 1.5rem;">
    <x-betting-bar
      label="Tu Apuesta"
      min="0"
      max="{{ max((float)$toCall * 3, (float)$heroStack) }}"
      step="0.1"
      value="{{ $toCall }}"
      unit="bb"
    />
  </div>

</div>
