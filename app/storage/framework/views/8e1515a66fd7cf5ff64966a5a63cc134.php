<?php $__env->startSection('content'); ?>
  <div style="max-width: 1200px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-12">
      <h1 class="text-4xl font-bold mb-3" style="color: #d1d5db;"><?php echo e(__('common.nav_app_name')); ?></h1>
      <p style="color: #9ca3af;" class="text-lg">Entrena y mejora tu juego de poker MTT</p>
    </div>

    <!-- Juego Principal -->
    <div style="margin-bottom: 3rem;">
      <p style="color: #d1d5db; font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: block;">🎮 SIMULADOR</p>
      <div class="grid grid-cols-1 gap-6 mb-12">
      <!-- MTT Simulator -->
      <a href="<?php echo e(route('mtt-simulator.index')); ?>" class="group rounded-lg transition transform hover:scale-105 hover:opacity-90" style="background-color: #1a212a; border: 1px solid #2e3a48;">
        <div class="p-8">
          <div class="mb-4 flex items-center gap-3">
            <div style="width: 48px; height: 48px; background-color: #047857; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 28px;">🎰</div>
            <h2 class="text-xl font-bold" style="color: #d1d5db;"><?php echo e(__('common.nav_mtt_simulator')); ?></h2>
          </div>
          <ul style="color: #9ca3af;" class="text-sm mb-4 ml-16 list-disc list-inside space-y-1">
            <li>Simula torneos MTT y mejora tu juego contra diferentes oponentes</li>
            <li>Selecciona tipos de board para practicar situaciones</li>
            <li>Selecciona cartas vistas para entender decisiones de rivales según sus perfiles</li>
            <li>Observa cómo juegan los bots en un torneo completo</li>
          </ul>
        </div>
      </a>
    </div>

    <!-- Herramientas de Configuración -->
    <div style="margin-bottom: 3rem;">
      <p style="color: #d1d5db; font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: block;">⚙️ CONFIGURACIÓN DEL JUEGO</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">

      <!-- Rivals (Bots) -->
      <a href="<?php echo e(route('bots.index')); ?>" class="group rounded-lg transition transform hover:scale-105 hover:opacity-90" style="background-color: #1a212a; border: 1px solid #2e3a48;">
        <div class="p-8">
          <div class="mb-4 flex items-center gap-3">
            <div style="width: 48px; height: 48px; background-color: #06b6d4; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 28px;">👥</div>
            <h2 class="text-xl font-bold" style="color: #d1d5db;"><?php echo e(__('common.nav_rivals')); ?></h2>
          </div>
          <ul style="color: #9ca3af;" class="text-sm mb-4 ml-16 list-disc list-inside space-y-1">
            <li>Crea y personaliza perfiles de rivales con diferentes estilos de juego</li>
            <li>Define el peso de las manos en diferentes posiciones</li>
            <li>Configura rangos y comportamientos específicos para cada rival</li>
          </ul>
        </div>
      </a>

      <!-- Hands (Hand Matrix) -->
      <a href="<?php echo e(route('hand-matrix.index')); ?>" class="group rounded-lg transition transform hover:scale-105 hover:opacity-90" style="background-color: #1a212a; border: 1px solid #2e3a48;">
        <div class="p-8">
          <div class="mb-4 flex items-center gap-3">
            <div style="width: 48px; height: 48px; background-color: #f59e0b; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 28px;">🃏</div>
            <h2 class="text-xl font-bold" style="color: #d1d5db;"><?php echo e(__('common.nav_hands')); ?></h2>
          </div>
          <ul style="color: #9ca3af;" class="text-sm mb-4 ml-16 list-disc list-inside space-y-1">
            <li>Crea matrices de juego con peso de manos</li>
            <li>Calcula valor esperado (EV) de tus decisiones in-game</li>
            <li>Verifica si estás jugando óptimo en tu posición</li>
            <li>Analiza rangos contra diferentes tipos de oponentes</li>
          </ul>
        </div>
      </a>

      <!-- Make Hands -->
      <a href="<?php echo e(route('made-hands.index')); ?>" class="group rounded-lg transition transform hover:scale-105 hover:opacity-90" style="background-color: #1a212a; border: 1px solid #2e3a48;">
        <div class="p-8">
          <div class="mb-4 flex items-center gap-3">
            <div style="width: 48px; height: 48px; background-color: #ec4899; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 28px;">✋</div>
            <h2 class="text-xl font-bold" style="color: #d1d5db;"><?php echo e(__('common.nav_make_hands')); ?></h2>
          </div>
          <ul style="color: #9ca3af;" class="text-sm mb-4 ml-16 list-disc list-inside space-y-1">
            <li>Configura pesos de manos hechas y proyectos</li>
            <li>Define la toma de decisiones del motor de los bots</li>
            <li>Crea casos de estudio para entrenamientos específicos</li>
          </ul>
        </div>
      </a>
      </div>
    </div>

    <!-- Info Cards -->
    <div style="border-top: 1px solid #2e3a48; padding-top: 32px;">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <p style="color: #047857;" class="text-sm font-semibold mb-2">🎯 OBJETIVO</p>
          <p style="color: #9ca3af;" class="text-sm">Mejorar tus habilidades en torneos MTT a través de entrenamiento estructurado</p>
        </div>
        <div>
          <p style="color: #06b6d4;" class="text-sm font-semibold mb-2">📊 ANÁLISIS</p>
          <p style="color: #9ca3af;" class="text-sm">Analiza diferentes tipos de manos y sus resultados contra diversos oponentes</p>
        </div>
        <div>
          <p style="color: #f59e0b;" class="text-sm font-semibold mb-2">⚡ PRÁCTICA</p>
          <p style="color: #9ca3af;" class="text-sm">Simula situaciones reales de torneo para perfeccionar tu estrategia</p>
        </div>
      </div>
    </div>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/dashboard.blade.php ENDPATH**/ ?>