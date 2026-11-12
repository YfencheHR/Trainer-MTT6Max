<?php $__env->startSection('content'); ?>
  <div x-data="gameContainer()" style="background-color: transparent; min-height: calc(100vh - 80px);">

    <!-- Header del Torneo -->
    <div style="background-color: #1a212a; border-bottom: 1px solid #2e3a48; padding: 1rem;">
      <div style="max-width: 1400px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold" style="color: #d1d5db;"><?php echo e($tournament->title); ?></h1>
          <p style="color: #9ca3af;" class="text-sm mt-1" x-data x-text="$store.tournamentInfo.data.big_blind ? ('Nivel ' + $store.tournamentInfo.data.level + ' · Ciegas ' + $store.tournamentInfo.data.small_blind + '/' + $store.tournamentInfo.data.big_blind + ' · ' + $store.tournamentInfo.data.players_alive + ' jugadores') : ''"></p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <button @click="pauseGame()" class="px-4 py-2 rounded font-semibold" style="background-color: #f59e0b; color: white;">
            <span x-show="!isPaused">Pausar</span>
            <span x-show="isPaused">Reanudar</span>
          </button>
          <a href="<?php echo e(route('mtt-simulator.history', $tournament)); ?>" class="px-4 py-2 rounded font-semibold" style="background-color: #1d4ed8; color: white; text-decoration: none;">📜 Historial</a>
          <a href="<?php echo e(route('mtt-simulator.index')); ?>" class="px-4 py-2 rounded font-semibold" style="background-color: #ef4444; color: white; text-decoration: none;">Salir</a>
        </div>
      </div>
    </div>

    <!-- Layout Principal: Mesa (3/4) + Info (1/4) -->
    <div style="max-width: 1400px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-6 flex flex-col-reverse lg:flex-row gap-3">

      <!-- Columna Derecha: Info + Log (1/4) -->
      <div class="w-full lg:w-1/4" style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Información del Torneo -->
        <?php if (isset($component)) { $__componentOriginalb25d0eb2c6bf50b3d34875c9f531f4da = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb25d0eb2c6bf50b3d34875c9f531f4da = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tournament-info','data' => ['tournament' => $tournament]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tournament-info'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tournament' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tournament)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb25d0eb2c6bf50b3d34875c9f531f4da)): ?>
<?php $attributes = $__attributesOriginalb25d0eb2c6bf50b3d34875c9f531f4da; ?>
<?php unset($__attributesOriginalb25d0eb2c6bf50b3d34875c9f531f4da); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb25d0eb2c6bf50b3d34875c9f531f4da)): ?>
<?php $component = $__componentOriginalb25d0eb2c6bf50b3d34875c9f531f4da; ?>
<?php unset($__componentOriginalb25d0eb2c6bf50b3d34875c9f531f4da); ?>
<?php endif; ?>

        <!-- Log del Juego -->
        <?php if (isset($component)) { $__componentOriginal63aebe9881252cc559c9d2583aa1571e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal63aebe9881252cc559c9d2583aa1571e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.game-log','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('game-log'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal63aebe9881252cc559c9d2583aa1571e)): ?>
<?php $attributes = $__attributesOriginal63aebe9881252cc559c9d2583aa1571e; ?>
<?php unset($__attributesOriginal63aebe9881252cc559c9d2583aa1571e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal63aebe9881252cc559c9d2583aa1571e)): ?>
<?php $component = $__componentOriginal63aebe9881252cc559c9d2583aa1571e; ?>
<?php unset($__componentOriginal63aebe9881252cc559c9d2583aa1571e); ?>
<?php endif; ?>
      </div>

      <!-- Mesa de Juego (3/4) -->
      <div class="w-full lg:w-3/4" style="min-width: 0;">
        <?php if (isset($component)) { $__componentOriginal91feea1417a75f84763e304563cc8225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91feea1417a75f84763e304563cc8225 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.game-table','data' => ['hand' => $hand,'currentUser' => auth()->user(),'noHudData' => $noHudData]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('game-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['hand' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hand),'currentUser' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(auth()->user()),'no-hud-data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($noHudData)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91feea1417a75f84763e304563cc8225)): ?>
<?php $attributes = $__attributesOriginal91feea1417a75f84763e304563cc8225; ?>
<?php unset($__attributesOriginal91feea1417a75f84763e304563cc8225); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91feea1417a75f84763e304563cc8225)): ?>
<?php $component = $__componentOriginal91feea1417a75f84763e304563cc8225; ?>
<?php unset($__componentOriginal91feea1417a75f84763e304563cc8225); ?>
<?php endif; ?>
      </div>
    </div>

    <!-- Módulos Adicionales (debajo) -->
    <div style="max-width: 1400px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Otras Mesas en Juego -->
        <?php if (isset($component)) { $__componentOriginal668b8768ab8750f1b8addc688ef93194 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal668b8768ab8750f1b8addc688ef93194 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.active-tables','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('active-tables'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal668b8768ab8750f1b8addc688ef93194)): ?>
<?php $attributes = $__attributesOriginal668b8768ab8750f1b8addc688ef93194; ?>
<?php unset($__attributesOriginal668b8768ab8750f1b8addc688ef93194); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal668b8768ab8750f1b8addc688ef93194)): ?>
<?php $component = $__componentOriginal668b8768ab8750f1b8addc688ef93194; ?>
<?php unset($__componentOriginal668b8768ab8750f1b8addc688ef93194); ?>
<?php endif; ?>

        <!-- Estadísticas de Mano -->
        <?php if (isset($component)) { $__componentOriginal66ed4740e118aa3022f78770571258dc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66ed4740e118aa3022f78770571258dc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.hand-statistics','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('hand-statistics'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal66ed4740e118aa3022f78770571258dc)): ?>
<?php $attributes = $__attributesOriginal66ed4740e118aa3022f78770571258dc; ?>
<?php unset($__attributesOriginal66ed4740e118aa3022f78770571258dc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal66ed4740e118aa3022f78770571258dc)): ?>
<?php $component = $__componentOriginal66ed4740e118aa3022f78770571258dc; ?>
<?php unset($__componentOriginal66ed4740e118aa3022f78770571258dc); ?>
<?php endif; ?>

        <!-- Historial de eliminaciones por puesto -->
        <?php if (isset($component)) { $__componentOriginala44cf7b8a7ccf74a1406b75bd43e6513 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala44cf7b8a7ccf74a1406b75bd43e6513 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.elimination-history','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('elimination-history'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala44cf7b8a7ccf74a1406b75bd43e6513)): ?>
<?php $attributes = $__attributesOriginala44cf7b8a7ccf74a1406b75bd43e6513; ?>
<?php unset($__attributesOriginala44cf7b8a7ccf74a1406b75bd43e6513); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala44cf7b8a7ccf74a1406b75bd43e6513)): ?>
<?php $component = $__componentOriginala44cf7b8a7ccf74a1406b75bd43e6513; ?>
<?php unset($__componentOriginala44cf7b8a7ccf74a1406b75bd43e6513); ?>
<?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function gameContainer() {
      return {
        isPaused: false,

        pauseGame() {
          this.isPaused = !this.isPaused;
        }
      }
    }
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/mtt-simulator/play.blade.php ENDPATH**/ ?>