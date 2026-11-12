<template>
  <div class="min-h-screen text-white flex flex-col" style="background-color: #12161c;">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50" style="background-color: #1a212a; border-bottom: 1px solid #2e3a48; margin-bottom: 22px;">
      <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <!-- Logo + Brand -->
          <Link href="/" class="flex items-center gap-3 hover:opacity-80 transition">
            <span class="h-8 w-8 bg-blue-500 rounded flex items-center justify-center text-xs font-bold">♠</span>
            <div class="flex flex-col">
              <span class="text-lg font-bold text-white">Trainer MTT</span>
              <span class="text-xs text-slate-400">6-max</span>
            </div>
          </Link>

          <!-- Menu Items -->
          <div class="app-nav hidden md:flex items-center h-16" style="margin-left: 60px;">
            <Link href="/">Dashboard</Link>
            <Link href="/bots">Rivales</Link>
            <a href="#trainer-manos">Trainer de manos</a>
            <a href="#mesa">Mesa de entrenamiento</a>
            <a href="#simulador">MTT</a>
            <a href="#tablas">Tablas</a>
            <a href="#importar">Importar</a>
          </div>

          <!-- BETA Badge -->
          <span class="app-beta">BETA</span>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1">
      <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<style scoped>
/* Navigation styling handled by app.css */
</style>
