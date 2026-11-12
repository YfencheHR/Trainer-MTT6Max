<template>
  <div class="container mx-auto py-8">
    <!-- Header -->
    <div class="mb-8">
      <Link href="/bots" class="text-accent-400 hover:text-accent-300 text-sm mb-4 inline-block">
        ← Volver a Rivales
      </Link>
      <h1 class="text-4xl font-bold text-white">Crear Nuevo Bot</h1>
    </div>

    <!-- Form -->
    <div class="card max-w-2xl">
      <form @submit.prevent="submit" class="space-y-6">
        <!-- Nombre -->
        <div>
          <label for="nombre-bot" class="block text-sm font-medium text-slate-300 mb-2">
            Nombre del Bot *
          </label>
          <input
            id="nombre-bot"
            v-model="form.nombre"
            type="text"
            class="input"
            placeholder="ej: Villano LAG"
            required
          />
          <p v-if="errors.nombre" class="text-red-400 text-sm mt-1">
            {{ errors.nombre }}
          </p>
        </div>

        <!-- Tipo -->
        <div>
          <label for="tipo-bot" class="block text-sm font-medium text-slate-300 mb-2">
            Tipo de Bot *
          </label>
          <select id="tipo-bot" v-model="form.tipo" class="input" required>
            <option value="">Selecciona un tipo</option>
            <option v-for="tipo in tipos" :key="tipo" :value="tipo">
              {{ formatTipo(tipo) }}
            </option>
          </select>
          <p v-if="errors.tipo" class="text-red-400 text-sm mt-1">
            {{ errors.tipo }}
          </p>
        </div>

        <!-- Perfil -->
        <div>
          <label for="perfil-bot" class="block text-sm font-medium text-slate-300 mb-2">
            Perfil (Cuadrante)
          </label>
          <select id="perfil-bot" v-model="form.perfil" class="input">
            <option value="">Sin perfil definido</option>
            <option v-for="perfil in perfiles" :key="perfil" :value="perfil">
              {{ perfil }}
            </option>
          </select>
        </div>

        <!-- VPIP/PFR -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="vpip" class="block text-sm font-medium text-slate-300 mb-2">
              VPIP (%)
            </label>
            <input
              id="vpip"
              v-model.number="form.vpip"
              type="number"
              min="0"
              max="100"
              class="input"
              placeholder="Ej: 25"
            />
          </div>
          <div>
            <label for="pfr" class="block text-sm font-medium text-slate-300 mb-2">
              PFR (%)
            </label>
            <input
              id="pfr"
              v-model.number="form.pfr"
              type="number"
              min="0"
              max="100"
              class="input"
              placeholder="Ej: 20"
            />
          </div>
        </div>

        <!-- Tilt Risk -->
        <div>
          <label for="tilt-risk" class="block text-sm font-medium text-slate-300 mb-2">
            Riesgo de Tilt (%)
          </label>
          <input
            id="tilt-risk"
            v-model.number="form.tilt_risk"
            type="number"
            min="0"
            max="100"
            class="input"
            placeholder="Ej: 15"
          />
          <p class="text-slate-400 text-xs mt-1">
            0% = Sin tilt | 100% = Tilt constante
          </p>
        </div>

        <!-- Profesional -->
        <div>
          <label class="flex items-center space-x-2 cursor-pointer">
            <input
              v-model="form.profesional"
              type="checkbox"
              class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-accent-500"
            />
            <span class="text-slate-300">Es un jugador profesional</span>
          </label>
        </div>

        <!-- Version Rango -->
        <div>
          <label for="version-rango" class="block text-sm font-medium text-slate-300 mb-2">
            Versión de Rango
          </label>
          <input
            id="version-rango"
            v-model="form.version_rango"
            type="text"
            class="input"
            placeholder="Ej: v1, v2-balanced"
          />
        </div>

        <!-- Buttons -->
        <div class="flex gap-4">
          <button type="submit" class="btn btn-primary" :disabled="processing">
            <span v-if="processing">Creando...</span>
            <span v-else>Crear Bot</span>
          </button>
          <Link href="/bots" class="btn btn-secondary">
            Cancelar
          </Link>
        </div>
      </form>
    </div>

    <!-- Presets -->
    <div class="mt-8">
      <h2 class="text-xl font-bold text-white mb-4">Perfiles Predefinidos</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button
          v-for="preset in presets"
          :key="preset.tipo"
          @click="loadPreset(preset)"
          class="card hover:bg-slate-700 transition-colors text-left"
        >
          <h3 class="font-bold text-white mb-2">{{ preset.nombre }}</h3>
          <p class="text-slate-400 text-sm mb-3">{{ preset.descripcion }}</p>
          <p class="text-xs text-slate-500">
            {{ preset.vpip }}% VPIP / {{ preset.pfr }}% PFR
          </p>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  tipos: Array,
  perfiles: Array,
});

const processing = ref(false);

const form = useForm({
  nombre: '',
  tipo: '',
  perfil: '',
  vpip: null,
  pfr: null,
  tilt_risk: null,
  profesional: false,
  version_rango: '',
});

const errors = ref({});

const presets = [
  {
    nombre: 'TAG',
    tipo: 'tag',
    descripcion: 'Tight-Aggressive, estándar ganador',
    vpip: 21,
    pfr: 19,
    perfil: 'tight-agresivo',
    profesional: true,
    tilt_risk: 10,
  },
  {
    nombre: 'LAG',
    tipo: 'lag',
    descripcion: 'Loose-Aggressive, dominador',
    vpip: 28,
    pfr: 26,
    perfil: 'loose-agresivo',
    profesional: true,
    tilt_risk: 15,
  },
  {
    nombre: 'Nit',
    tipo: 'nit',
    descripcion: 'Tight-Aggressive, selectivo',
    vpip: 16,
    pfr: 14,
    perfil: 'tight-agresivo',
    profesional: true,
    tilt_risk: 5,
  },
];

const formatTipo = (tipo) => {
  const map = {
    'setminer': 'Setminer',
    'roca': 'Roca',
    'nit': 'Nit',
    'omc': 'OMC',
    'tag': 'TAG',
    'lag': 'LAG',
    'calling_station': 'Calling Station',
    'maniaco': 'Maníaco',
    'ballena': 'Ballena',
    'usuario_real': 'Usuario Real',
    'pool': 'Pool GTO',
  };
  return map[tipo] || tipo;
};

const loadPreset = (preset) => {
  form.nombre = preset.nombre;
  form.tipo = preset.tipo;
  form.vpip = preset.vpip;
  form.pfr = preset.pfr;
  form.perfil = preset.perfil;
  form.profesional = preset.profesional;
  form.tilt_risk = preset.tilt_risk;
};

const submit = () => {
  processing.value = true;
  form.post('/bots', {
    onFinish: () => {
      processing.value = false;
    },
    onError: (err) => {
      errors.value = err;
    }
  });
};
</script>
