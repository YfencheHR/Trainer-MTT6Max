<template>
  <div class="container mx-auto py-8">
    <!-- Header -->
    <div class="mb-8">
      <Link :href="`/bots/${bot.id}`" class="text-accent-400 hover:text-accent-300 text-sm mb-4 inline-block">
        ← Volver a {{ bot.nombre }}
      </Link>
      <h1 class="text-4xl font-bold text-white">Editar: {{ bot.nombre }}</h1>
    </div>

    <!-- Form -->
    <div class="card max-w-2xl">
      <form @submit.prevent="submit" class="space-y-6">
        <!-- Nombre -->
        <div>
          <label for="bot-nombre" class="block text-sm font-medium text-slate-300 mb-2">
            Nombre del Bot *
          </label>
          <input
            id="bot-nombre"
            v-model="form.nombre"
            type="text"
            class="input"
            required
          />
          <p v-if="errors.nombre" class="text-red-400 text-sm mt-1">
            {{ errors.nombre }}
          </p>
        </div>

        <!-- Tipo -->
        <div>
          <label for="bot-tipo" class="block text-sm font-medium text-slate-300 mb-2">
            Tipo de Bot *
          </label>
          <select id="bot-tipo" v-model="form.tipo" class="input" required>
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
          <label for="bot-perfil" class="block text-sm font-medium text-slate-300 mb-2">
            Perfil (Cuadrante)
          </label>
          <select id="bot-perfil" v-model="form.perfil" class="input">
            <option value="">Sin perfil definido</option>
            <option v-for="perfil in perfiles" :key="perfil" :value="perfil">
              {{ perfil }}
            </option>
          </select>
        </div>

        <!-- VPIP/PFR -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="bot-vpip" class="block text-sm font-medium text-slate-300 mb-2">
              VPIP (%)
            </label>
            <input
              id="bot-vpip"
              v-model.number="form.vpip"
              type="number"
              min="0"
              max="100"
              class="input"
            />
          </div>
          <div>
            <label for="bot-pfr" class="block text-sm font-medium text-slate-300 mb-2">
              PFR (%)
            </label>
            <input
              id="bot-pfr"
              v-model.number="form.pfr"
              type="number"
              min="0"
              max="100"
              class="input"
            />
          </div>
        </div>

        <!-- Tilt Risk -->
        <div>
          <label for="bot-tilt-risk" class="block text-sm font-medium text-slate-300 mb-2">
            Riesgo de Tilt (%)
          </label>
          <input
            id="bot-tilt-risk"
            v-model.number="form.tilt_risk"
            type="number"
            min="0"
            max="100"
            class="input"
          />
          <p class="text-slate-400 text-xs mt-1">
            0% = No tiltea | 100% = Tiltea constantemente
          </p>
        </div>

        <!-- Profesional -->
        <div>
          <label for="bot-profesional" class="flex items-center space-x-2 cursor-pointer">
            <input
              id="bot-profesional"
              v-model="form.profesional"
              type="checkbox"
              class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-accent-500"
            />
            <span class="text-slate-300">Es un jugador profesional</span>
          </label>
        </div>

        <!-- Version Rango -->
        <div>
          <label for="bot-version-rango" class="block text-sm font-medium text-slate-300 mb-2">
            Versión de Rango
          </label>
          <input
            id="bot-version-rango"
            v-model="form.version_rango"
            type="text"
            class="input"
          />
        </div>

        <!-- Buttons -->
        <div class="flex gap-4">
          <button type="submit" class="btn btn-primary" :disabled="processing">
            <span v-if="processing">Guardando...</span>
            <span v-else>Guardar Cambios</span>
          </button>
          <Link :href="`/bots/${bot.id}`" class="btn btn-secondary">
            Cancelar
          </Link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  bot: Object,
  tipos: Array,
  perfiles: Array,
});

const processing = ref(false);

const form = useForm({
  nombre: props.bot.nombre,
  tipo: props.bot.tipo,
  perfil: props.bot.perfil,
  vpip: props.bot.vpip,
  pfr: props.bot.pfr,
  tilt_risk: props.bot.tilt_risk,
  profesional: props.bot.profesional,
  version_rango: props.bot.version_rango,
});

const errors = ref({});

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

const submit = () => {
  processing.value = true;
  form.patch(`/bots/${props.bot.id}`, {
    onFinish: () => {
      processing.value = false;
    },
    onError: (err) => {
      errors.value = err;
    }
  });
};
</script>
