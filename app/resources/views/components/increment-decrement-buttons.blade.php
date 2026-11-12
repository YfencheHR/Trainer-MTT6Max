@props(['size' => 'normal', 'onIncrement' => '', 'onDecrement' => ''])

@php
  $isSmall = $size === 'small';

  $buttonClass = $isSmall
    ? 'px-1 py-0.5 text-xs'
    : 'px-1.5 py-1 text-xs';

  $gapClass = $isSmall ? 'gap-0' : 'gap-0.5';
@endphp

<div class="flex {{ $gapClass }}">
  <button
    type="button"
    @click="{{ $onDecrement }}"
    style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
    class="rounded border hover:text-white hover:border-slate-500 transition font-semibold {{ $buttonClass }}"
    title="Restar"
  >
    −
  </button>

  <button
    type="button"
    @click="{{ $onIncrement }}"
    style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
    class="rounded border hover:text-white hover:border-slate-500 transition font-semibold {{ $buttonClass }}"
    title="Sumar"
  >
    +
  </button>
</div>
