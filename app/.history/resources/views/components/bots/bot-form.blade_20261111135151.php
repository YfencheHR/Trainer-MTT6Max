@props(['bot' => null, 'copyFrom' => null, 'isEditing' => false])

@php
  $bot = $bot ?? $copyFrom;
@endphp

<!-- Información General -->
<div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 mb-8">
  <h2 class="text-2xl font-bold text-white mb-4">{{ __('bots.general_info') }}</h2>

  <div class="mb-6">
    <label for="nombre" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2">{{ __('bots.name') }} *</label>
    <input type="text" id="nombre" name="nombre" value="{{ $bot ? ($copyFrom ? $bot->nombre . ' (Copia)' : $bot->nombre) : old('nombre') }}" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border" required>
    @error('nombre') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div>
      <label for="tipo" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2">{{ __('bots.type') }} *</label>
      <input type="text" id="tipo" name="tipo" value="{{ $bot ? $bot->tipo : old('tipo') }}" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border" required>
      @error('tipo') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
      <label for="perfil" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2">{{ __('bots.profile') }}</label>
      <input type="text" id="perfil" name="perfil" value="{{ $bot ? $bot->perfil : old('perfil') }}" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border">
    </div>
  </div>

  <div class="mb-6" x-data="iconPicker(@js($bot ? $bot->icono : ''), @js(\App\Config\IconConfig::getGroups()), @js($bot ? \App\Config\IconConfig::forTipo($bot->tipo, $bot->perfil) : '🤖'))">
    <label style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2">{{ __('bots.icon') }}</label>
    <input type="hidden" name="icono" :value="icon">

    <div class="flex flex-wrap items-center gap-3">
      <div style="background: linear-gradient(135deg, #10b981, #059669); width: 56px; height: 56px; font-size: 1.9rem;" class="rounded-lg flex items-center justify-center" x-text="icon || fallback"></div>
      <button type="button" @click="open = !open" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="px-4 py-3 rounded border hover:border-slate-500">{{ __('bots.select_icon') }}</button>
      <button type="button" x-show="icon" @click="icon = ''" style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;" class="px-3 py-3 rounded border hover:border-slate-500 text-sm" title="Usar el icono por defecto de su tipo">Por defecto</button>
      <span style="color: #9ca3af;" class="text-sm" x-show="hovered" x-text="hovered"></span>
    </div>

    <div x-show="open" x-cloak style="background-color: #1a212a; border-color: #2e3a48;" class="mt-3 rounded border p-4">
      <div class="flex flex-wrap gap-2 mb-3">
        <input type="search" x-model="q" placeholder="Buscar (ej. tiburón, farol, lobo…)" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="flex-1 min-w-[12rem] px-3 py-2 rounded border text-sm">
        <div class="flex gap-2">
          <input type="text" x-model="custom" maxlength="12" placeholder="Otro símbolo" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff; width: 9rem;" class="px-3 py-2 rounded border text-sm" @keydown.enter.prevent="useCustom()">
          <button type="button" @click="useCustom()" style="background-color: #374151;" class="px-3 py-2 rounded text-white text-sm hover:opacity-80">Usar</button>
        </div>
      </div>
      <p style="color: #6b7280;" class="text-xs mb-3">¿Hay un tipo de jugador nuevo? Pega cualquier emoji en «Otro símbolo» y pulsa Usar. Para añadirlo a la colección, edita <code>app/Config/icons.json</code>.</p>

      <div style="max-height: 340px; overflow-y: auto;">
        <template x-for="g in filtered()" :key="g.name">
          <div class="mb-4">
            <p style="color: #10b981;" class="text-xs uppercase font-bold mb-2" x-text="g.name"></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(48px, 1fr)); gap: 8px;">
              <template x-for="it in g.icons" :key="it.i">
                <button type="button" @click="pick(it.i)" @mouseenter="hovered = it.i + ' ' + it.n" @mouseleave="hovered = ''" :title="it.n"
                  :style="'background-color: #0f1419; aspect-ratio: 1; font-size: 1.5rem; border: 2px solid ' + (it.i === icon ? '#10b981' : 'transparent')"
                  class="flex items-center justify-center rounded hover:opacity-70 transition" x-text="it.i"></button>
              </template>
            </div>
          </div>
        </template>
        <p x-show="filtered().length === 0" style="color: #9ca3af;" class="text-sm">Ningún icono coincide: usa «Otro símbolo».</p>
      </div>
    </div>
  </div>

  @once
    <script>
      function iconPicker(initial, groups, fallback) {
        return {
          icon: initial || '', groups, fallback, open: false, q: '', custom: '', hovered: '',
          pick(i) { this.icon = i; this.open = false; this.hovered = ''; },
          useCustom() { const v = this.custom.trim(); if (v) { this.icon = v; this.custom = ''; this.open = false; } },
          filtered() {
            const q = this.q.trim().toLowerCase();
            if (!q) return this.groups;
            return this.groups.map(g => ({ name: g.name, icons: g.icons.filter(i => i.n.toLowerCase().includes(q) || i.i.includes(q)) })).filter(g => g.icons.length);
          },
        };
      }
    </script>
  @endonce

  <div>
    <label style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2">{{ __('bots.description') }}</label>
    <textarea name="descripcion" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border" rows="4">{{ $bot ? $bot->descripcion : old('descripcion') }}</textarea>
  </div>
</div>

<!-- Estadísticas Generales -->
<div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 mb-8">
  <h2 class="text-2xl font-bold text-white mb-4">{{ __('bots.general_stats') }}</h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <x-bots.stat-input-with-tooltip
      :label="__('bots.vpip') . ' (%)'"
      stat="vpip"
      :value="$bot ? $bot->vpip : old('vpip')"
      fieldName="vpip"
    />
    <x-bots.stat-input-with-tooltip
      :label="__('bots.pfr') . ' (%)'"
      stat="pfr"
      :value="$bot ? $bot->pfr : old('pfr')"
      fieldName="pfr"
    />
    <x-bots.stat-input-with-tooltip
      :label="__('bots.tilt_risk') . ' (%)'"
      stat="tilt"
      :value="$bot ? $bot->tilt_risk : old('tilt_risk')"
      fieldName="tilt_risk"
    />
  </div>

  <div class="flex items-end">
    <label class="flex items-center">
      <input type="checkbox" name="profesional" value="1" {{ $bot && $bot->profesional ? 'checked' : '' }} class="mr-3 w-4 h-4">
      <span style="color: #9ca3af;" class="text-sm uppercase font-bold">{{ __('bots.professional') }}</span>
    </label>
  </div>
</div>

@if($copyFrom)
  <input type="hidden" name="copy_from" value="{{ $copyFrom->id }}">
@endif

<style>
  /* Ocultar el incrementador nativo de los campos numéricos */
  input[type="number"]::-webkit-outer-spin-button,
  input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  input[type="number"] {
    -moz-appearance: textfield;
  }
</style>
