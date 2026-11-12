<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['bot' => null, 'copyFrom' => null, 'isEditing' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['bot' => null, 'copyFrom' => null, 'isEditing' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
  $bot = $bot ?? $copyFrom;
?>

<!-- Información General -->
<div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 mb-8">
  <h2 class="text-2xl font-bold text-white mb-4"><?php echo e(__('bots.general_info')); ?></h2>

  <div class="mb-6">
    <label for="nombre" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2"><?php echo e(__('bots.name')); ?> *</label>
    <input type="text" id="nombre" name="nombre" value="<?php echo e($bot ? ($copyFrom ? $bot->nombre . ' (Copia)' : $bot->nombre) : old('nombre')); ?>" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border" required>
    <?php $__errorArgs = ['nombre'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div>
      <label for="tipo" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2"><?php echo e(__('bots.type')); ?> *</label>
      <input type="text" id="tipo" name="tipo" value="<?php echo e($bot ? $bot->tipo : old('tipo')); ?>" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border" required>
      <?php $__errorArgs = ['tipo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
      <label for="perfil" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2"><?php echo e(__('bots.profile')); ?></label>
      <input type="text" id="perfil" name="perfil" value="<?php echo e($bot ? $bot->perfil : old('perfil')); ?>" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border">
    </div>
  </div>

  <div class="mb-6" x-data="iconPicker(<?php echo \Illuminate\Support\Js::from($bot ? $bot->icono : '')->toHtml() ?>, <?php echo \Illuminate\Support\Js::from(\App\Config\IconConfig::getGroups())->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($bot ? \App\Config\IconConfig::forTipo($bot->tipo, $bot->perfil) : '🤖')->toHtml() ?>)">
    <label for="icon-picker-button" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2"><?php echo e(__('bots.icon')); ?></label>
    <input type="hidden" name="icono" :value="icon">

    <div class="flex flex-wrap items-center gap-3">
      <div style="background: linear-gradient(135deg, #10b981, #059669); width: 56px; height: 56px; font-size: 1.9rem;" class="rounded-lg flex items-center justify-center" x-text="icon || fallback"></div>
      <button type="button" id="icon-picker-button" @click="open = !open" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="px-4 py-3 rounded border hover:border-slate-500"><?php echo e(__('bots.select_icon')); ?></button>
      <button type="button" x-show="icon" @click="icon = ''" style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;" class="px-3 py-3 rounded border hover:border-slate-500 text-sm" title="Usar el icono por defecto de su tipo">Por defecto</button>
      <span style="color: #9ca3af;" class="text-sm" x-show="hovered" x-text="hovered"></span>
    </div>

    <div x-show="open" x-cloak style="background-color: #1a212a; border-color: #2e3a48;" class="mt-3 rounded border p-4">
      <div class="flex flex-wrap gap-2 mb-3">
        <label for="icon-search" class="sr-only">Buscar iconos</label>
        <input type="search" id="icon-search" x-model="q" placeholder="Buscar (ej. tiburón, farol, lobo…)" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="flex-1 min-w-[12rem] px-3 py-2 rounded border text-sm">
        <div class="flex gap-2">
          <label for="icon-custom" class="sr-only">Otro símbolo</label>
          <input type="text" id="icon-custom" x-model="custom" maxlength="12" placeholder="Otro símbolo" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff; width: 9rem;" class="px-3 py-2 rounded border text-sm" @keydown.enter.prevent="useCustom()">
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

  <?php if (! $__env->hasRenderedOnce('b7c22aae-ee5b-4a81-8005-6792f4cb9e17')): $__env->markAsRenderedOnce('b7c22aae-ee5b-4a81-8005-6792f4cb9e17'); ?>
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
  <?php endif; ?>

  <div>
    <label for="descripcion" style="color: #9ca3af;" class="block text-sm uppercase font-bold mb-2"><?php echo e(__('bots.description')); ?></label>
    <textarea id="descripcion" name="descripcion" style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;" class="w-full px-4 py-3 rounded border" rows="4"><?php echo e($bot ? $bot->descripcion : old('descripcion')); ?></textarea>
  </div>
</div>

<!-- Estadísticas Generales -->
<div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 mb-8">
  <h2 class="text-2xl font-bold text-white mb-4"><?php echo e(__('bots.general_stats')); ?></h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <?php if (isset($component)) { $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.stat-input-with-tooltip','data' => ['label' => __('bots.vpip') . ' (%)','stat' => 'vpip','value' => $bot ? $bot->vpip : old('vpip'),'fieldName' => 'vpip']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.stat-input-with-tooltip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.vpip') . ' (%)'),'stat' => 'vpip','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bot ? $bot->vpip : old('vpip')),'fieldName' => 'vpip']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $attributes = $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $component = $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.stat-input-with-tooltip','data' => ['label' => __('bots.pfr') . ' (%)','stat' => 'pfr','value' => $bot ? $bot->pfr : old('pfr'),'fieldName' => 'pfr']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.stat-input-with-tooltip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.pfr') . ' (%)'),'stat' => 'pfr','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bot ? $bot->pfr : old('pfr')),'fieldName' => 'pfr']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $attributes = $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $component = $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.stat-input-with-tooltip','data' => ['label' => __('bots.tilt_risk') . ' (%)','stat' => 'tilt','value' => $bot ? $bot->tilt_risk : old('tilt_risk'),'fieldName' => 'tilt_risk']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.stat-input-with-tooltip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.tilt_risk') . ' (%)'),'stat' => 'tilt','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bot ? $bot->tilt_risk : old('tilt_risk')),'fieldName' => 'tilt_risk']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $attributes = $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $component = $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
  </div>

  <div class="flex items-end">
    <label class="flex items-center">
      <input type="checkbox" name="profesional" value="1" <?php echo e($bot && $bot->profesional ? 'checked' : ''); ?> class="mr-3 w-4 h-4">
      <span style="color: #9ca3af;" class="text-sm uppercase font-bold"><?php echo e(__('bots.professional')); ?></span>
    </label>
  </div>
</div>

<?php if($copyFrom): ?>
  <input type="hidden" name="copy_from" value="<?php echo e($copyFrom->id); ?>">
<?php endif; ?>

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
<?php /**PATH /app/resources/views/components/bots/bot-form.blade.php ENDPATH**/ ?>