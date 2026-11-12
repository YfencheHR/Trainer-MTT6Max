<nav class="sticky top-0 z-50" style="background-color: #1a212a; border-bottom: 1px solid #2e3a48; margin-bottom: 22px;">
  <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <a href="{{ route('dashboard') }}" class="flex items-center gap-3 hover:opacity-80 transition">
        <img src="/images/poker-trainer-icon.png" alt="{{ __('common.nav_logo') }}" style="width: 40px; height: 40px; object-fit: contain;">
        <span class="text-lg font-bold text-white">{{ __('common.nav_app_name') }}</span>
      </a>

      <div class="hidden md:flex items-center gap-8">
        <a href="{{ route('mtt-simulator.index') }}" class="text-slate-400 hover:text-blue-400 transition">{{ __('common.nav_mtt_simulator') }}</a>
        <a href="{{ route('bots.index') }}" class="text-slate-400 hover:text-blue-400 transition">{{ __('common.nav_rivals') }}</a>
        <a href="{{ route('hand-matrix.index') }}" class="text-slate-400 hover:text-blue-400 transition">{{ __('common.nav_hands') }}</a>
        <a href="{{ route('made-hands.index') }}" class="text-slate-400 hover:text-blue-400 transition">{{ __('common.nav_make_hands') }}</a>
        <a href="{{ route('import.index') }}" class="transition {{ request()->routeIs('import.*') ? 'text-blue-400' : 'text-slate-400 hover:text-blue-400' }}">{{ __('common.nav_import') }}</a>
      </div>
      <span class="bg-blue-600 text-white px-3 py-1 rounded text-xs font-bold">{{ __('common.nav_beta') }}</span>
    </div>
  </div>
</nav>
