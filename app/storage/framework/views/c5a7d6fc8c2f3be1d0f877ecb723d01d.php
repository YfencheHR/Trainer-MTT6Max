<?php $__env->startSection('content'); ?>
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Encabezado -->
    <div class="flex justify-between items-center mb-8">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <span class="text-4xl"><?php echo e($bot->icono ?? '🤖'); ?></span>
          <h1 class="text-4xl font-bold text-white"><?php echo e($bot->nombre); ?></h1>
        </div>
        <p style="color: #9ca3af;" class="text-lg"><?php echo e(ucfirst($bot->tipo)); ?> • <?php echo e($bot->perfil ?? 'N/A'); ?> <?php if($bot->profesional): ?><span style="background-color: #10b981; color: white;" class="inline-block ml-2 px-2 py-1 text-xs rounded">PRO</span><?php endif; ?></p>
      </div>
      <?php if (isset($component)) { $__componentOriginal55f322382dda8ab9b5cda520c15cb4c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal55f322382dda8ab9b5cda520c15cb4c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal-popup','data' => ['id' => 'deleteModal','title' => __('bots.delete'),'message' => __('bots.confirm_delete'),'primaryBtnText' => __('bots.delete'),'secondaryBtnText' => __('common.cancel'),'primaryBtnColor' => 'ef4444','onConfirm' => 'document.getElementById(\'deleteForm\').submit()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'deleteModal','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.delete')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.confirm_delete')),'primaryBtnText' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.delete')),'secondaryBtnText' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('common.cancel')),'primaryBtnColor' => 'ef4444','onConfirm' => 'document.getElementById(\'deleteForm\').submit()']); ?>

        <div class="flex gap-2">
          <a href="<?php echo e(route('bots.edit', $bot)); ?>" style="background-color: #f59e0b;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition"><?php echo e(__('bots.edit')); ?></a>
          <button type="button" style="background-color: #ef4444;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition" @click="deleteModal = true"><?php echo e(__('bots.delete')); ?></button>
          <form method="POST" action="<?php echo e(route('bots.duplicate', $bot)); ?>" style="display: inline;">
            <?php echo csrf_field(); ?>
            <button type="submit" style="background-color: #8b5cf6;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition"><?php echo e(__('bots.copy')); ?></button>
          </form>
        </div>

        <!-- Delete Form (hidden) -->
        <form id="deleteForm" method="POST" action="<?php echo e(route('bots.destroy', $bot)); ?>" style="display: none;">
          <?php echo csrf_field(); ?>
          <?php echo method_field('DELETE'); ?>
        </form>
       <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal55f322382dda8ab9b5cda520c15cb4c3)): ?>
<?php $attributes = $__attributesOriginal55f322382dda8ab9b5cda520c15cb4c3; ?>
<?php unset($__attributesOriginal55f322382dda8ab9b5cda520c15cb4c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal55f322382dda8ab9b5cda520c15cb4c3)): ?>
<?php $component = $__componentOriginal55f322382dda8ab9b5cda520c15cb4c3; ?>
<?php unset($__componentOriginal55f322382dda8ab9b5cda520c15cb4c3); ?>
<?php endif; ?>
    </div>

    <!-- Descripción -->
    <?php if($bot->descripcion): ?>
      <div style="background-color: #1a212a; border-color: #2e3a48;" class="mb-8 p-6 rounded border">
        <p style="color: #ffffff;" class="text-base"><?php echo e($bot->descripcion); ?></p>
      </div>
    <?php endif; ?>

    <?php if($bot->isPool()): ?>
      <?php echo $__env->make('bots._pool-panel', ['bot' => $bot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <!-- Estadísticas Generales -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-white mb-4"><?php echo e(__('bots.general_stats')); ?></h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- VPIP -->
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="p-6 rounded border">
          <p style="color: #9ca3af;" class="text-xs uppercase mb-2"><?php echo e(__('bots.vpip')); ?></p>
          <p style="color: <?php echo e(\App\Config\StatColor::getColor('vpip', $bot->vpip)); ?>;" class="text-4xl font-bold"><?php echo e($bot->vpip); ?>%</p>
        </div>

        <!-- PFR -->
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="p-6 rounded border">
          <p style="color: #9ca3af;" class="text-xs uppercase mb-2"><?php echo e(__('bots.pfr')); ?></p>
          <p style="color: <?php echo e(\App\Config\StatColor::getColor('pfr', $bot->pfr)); ?>;" class="text-4xl font-bold"><?php echo e($bot->pfr); ?>%</p>
        </div>

        <!-- Tilt Risk -->
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="p-6 rounded border">
          <p style="color: #9ca3af;" class="text-xs uppercase mb-2"><?php echo e(__('bots.tilt_risk')); ?></p>
          <p style="color: <?php echo e(\App\Config\StatColor::getColor('tilt', $bot->tilt_risk)); ?>;" class="text-4xl font-bold"><?php echo e($bot->tilt_risk); ?>%</p>
        </div>
      </div>
    </div>

    <!-- Estadísticas por calle -->
    <?php
      $general = $bot->generalStats();
      $posiciones = $bot->positionStats();
      $roles = $bot->postflopRoles();
      $streets = \App\Config\BotStreets::STREETS;
      $colored = ['vpip', 'pfr', 'three_bet', 'four_bet'];
    ?>
    <div class="mb-8">
      <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
        <h2 class="text-2xl font-bold text-white">Estadísticas por calle poyas jontas</h2>
        <?php if($general && $general->sample_size): ?>
          <p style="color: #9ca3af;" class="text-sm">Calculadas con <span style="color: #10b981;" class="font-bold"><?php echo e(number_format($general->sample_size, 0, ',', '.')); ?></span> manos · confianza <?php echo e(round(($general->confidence ?? 0) * 100)); ?>%</p>
        <?php elseif($general): ?>
          <p style="color: #f59e0b;" class="text-sm">Cifras provisionales (sin muestra real): importa datos de PokerTracker para sustituirlas</p>
        <?php endif; ?>
      </div>

      <?php if($general): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <?php $__currentLoopData = $streets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $street): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
              <h3 class="text-lg font-bold text-white mb-4"><?php echo e($street['nombre']); ?></h3>

              <p style="color: #10b981;" class="text-xs uppercase font-bold mb-2"><?php echo e(__('bots.rigurosas')); ?></p>
              <div class="grid grid-cols-3 gap-3 mb-5">
                <?php $__currentLoopData = $street['stats']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php $v = $general->{$stat}; ?>
                  <div style="background-color: #11161c;" class="rounded p-3">
                    <p style="color: #9ca3af;" class="text-xs mb-1"><?php echo e(\App\Config\BotStreets::LABELS[$stat]); ?></p>
                    <p style="color: <?php echo e($v !== null && in_array($stat, $colored) ? \App\Config\StatColor::getColor($stat, $v) : ($v === null ? '#4b5563' : '#e5e7eb')); ?>;" class="font-bold text-lg"><?php echo e(\App\Config\BotStreets::format($stat, $v)); ?></p>
                  </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>

              <p style="color: #f59e0b;" class="text-xs uppercase font-bold mb-2"><?php echo e(__('bots.adaptativas')); ?></p>
              <div class="grid grid-cols-3 gap-3">
                <?php $__currentLoopData = $street['adaptativas']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php $v = $general->{$stat}; ?>
                  <div style="background-color: #11161c;" class="rounded p-3">
                    <p style="color: #9ca3af;" class="text-xs mb-1"><?php echo e(\App\Config\BotStreets::LABELS[$stat]); ?></p>
                    <p style="color: <?php echo e($v === null ? '#4b5563' : '#e5e7eb'); ?>;" class="font-bold text-lg"><?php echo e(\App\Config\BotStreets::format($stat, $v)); ?></p>
                  </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      <?php else: ?>
        <div style="background-color: #1a212a; border-color: #2e3a48; color: #9ca3af;" class="rounded border p-6 text-sm"><?php echo e(__('bots.not_available')); ?></div>
      <?php endif; ?>
    </div>

    <!-- Estadísticas por posición (datos importados) -->
    <?php if($posiciones): ?>
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-1">Por posición</h2>
        <p style="color: #9ca3af;" class="text-sm mb-4">Apertura, 3-bet, call en frío y respuesta a 3-bet según dónde se sienta. Son las cifras que usa el cerebro del bot en cada posición.</p>
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 overflow-x-auto">
          <table class="w-full text-sm" style="color: #d1d5db; min-width: 640px;">
            <thead>
              <tr style="color: #9ca3af;" class="text-left text-xs uppercase">
                <th class="pb-2">Posición</th>
                <?php $__currentLoopData = ['rfi' => 'RFI', 'vpip' => 'VPIP', 'pfr' => 'PFR', 'three_bet' => '3-Bet', 'cold_call' => 'Call en frío', 'fold_to_3bet' => 'Fold a 3-Bet', 'four_bet' => '4-Bet', 'squeeze' => 'Squeeze']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <th class="pb-2"><?php echo e($name); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <th class="pb-2 text-right">Manos</th>
              </tr>
            </thead>
            <tbody>
              <?php $__currentLoopData = $posiciones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pos => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr style="border-top: 1px solid #2e3a48;">
                  <td class="py-2 font-semibold text-white"><?php echo e($pos); ?></td>
                  <?php $__currentLoopData = ['rfi', 'vpip', 'pfr', 'three_bet', 'cold_call', 'fold_to_3bet', 'four_bet', 'squeeze']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <td style="color: <?php echo e(isset($r[$col]) ? '#e5e7eb' : '#4b5563'); ?>;"><?php echo e(\App\Config\BotStreets::format($col, $r[$col] ?? null)); ?></td>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  <td class="text-right" style="color: #9ca3af;"><?php echo e(number_format($r['hands'] ?? 0, 0, ',', '.')); ?></td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
    <!-- Agresión por calle: en posición / fuera de posición, como agresor y como defensor -->
    <?php if($roles): ?>
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-1">Agresión por calle: IP vs OOP</h2>
        <p style="color: #9ca3af;" class="text-sm mb-4">
          <b style="color:#e5e7eb;">Como agresor</b> (tenía la iniciativa): cuánto apuesta (c-bet y barriles). <b style="color:#e5e7eb;">Como defensor</b>: qué hace ante esa apuesta.
          IP = en posición · OOP = fuera de posición.
        </p>
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 overflow-x-auto">
          <table class="w-full text-sm" style="color: #d1d5db; min-width: 640px;">
            <thead>
              <tr style="color: #9ca3af;" class="text-left text-xs uppercase">
                <th class="pb-2">Calle</th><th class="pb-2">Posición</th>
                <th class="pb-2" style="color:#10b981;">Agresor: apuesta</th>
                <th class="pb-2" style="color:#f59e0b;">Defensor: fold</th><th class="pb-2" style="color:#f59e0b;">call</th><th class="pb-2" style="color:#f59e0b;">raise</th>
                <th class="pb-2 text-right">Ocasiones (agr / def)</th>
              </tr>
            </thead>
            <tbody>
              <?php $__currentLoopData = ['flop' => 'Flop', 'turn' => 'Turn', 'river' => 'River']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $street => $streetName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $__currentLoopData = ['ip' => 'IP', 'oop' => 'OOP']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ip => $ipName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php $r = $roles[$street][$ip] ?? null; ?>
                  <?php if($r): ?>
                    <tr style="border-top: 1px solid #2e3a48;">
                      <td class="py-2 font-semibold text-white"><?php echo e($ip === 'ip' ? $streetName : ''); ?></td>
                      <td><?php echo e($ipName); ?></td>
                      <td style="color: <?php echo e(isset($r['agg']['bet']) ? '#e5e7eb' : '#4b5563'); ?>;"><?php echo e(\App\Config\BotStreets::format('cbet', $r['agg']['bet'] ?? null)); ?></td>
                      <?php $__currentLoopData = ['fold', 'call', 'raise']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td style="color: <?php echo e(isset($r['def'][$k]) ? '#e5e7eb' : '#4b5563'); ?>;"><?php echo e(\App\Config\BotStreets::format('cbet', $r['def'][$k] ?? null)); ?></td>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                      <td class="text-right" style="color: #9ca3af;"><?php echo e(number_format($r['agg']['n'] ?? 0, 0, ',', '.')); ?> / <?php echo e(number_format($r['def']['n'] ?? 0, 0, ',', '.')); ?></td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if($bot->isPool()): ?>
      <?php echo $__env->make('bots._pool-preflop-tables', ['tables' => $preflopTables], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/bots/show.blade.php ENDPATH**/ ?>