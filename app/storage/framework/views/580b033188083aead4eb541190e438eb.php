<?php $__env->startSection('content'); ?>
  <div style="max-width: 1200px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold mb-8" style="color: #d1d5db;"><?php echo e(__('tournaments.create_title')); ?></h1>

    <?php if($errors->any()): ?>
      <div class="mb-6 p-4 rounded" style="background-color: #3a1f1f; border-left: 4px solid #ef4444;">
        <p style="color: #ef4444;" class="font-semibold"><?php echo e(__('common.validation_error')); ?></p>
        <ul style="color: #fca5a5;" class="mt-2 list-disc list-inside">
          <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e($error); ?></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('mtt-simulator.store')); ?>" style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-8 space-y-8">
      <?php echo csrf_field(); ?>

      <!-- Initial Parameters Section -->
      <div class="grid gap-6" style="grid-template-columns: repeat(3, 1fr);">
        <!-- Row 1: Starting Stack, Big Blind, Total Players -->
        <?php if (isset($component)) { $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.number-input-with-slider','data' => ['id' => 'startingStack','name' => 'starting_stack','label' => ''.e(__('tournaments.starting_stack')).'','value' => ''.e(old('starting_stack', $defaults['starting_stack']['default'])).'','min' => ''.e($defaults['starting_stack']['min']).'','max' => ''.e($defaults['starting_stack']['max']).'','step' => ''.e($defaults['starting_stack']['step']).'','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('number-input-with-slider'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'startingStack','name' => 'starting_stack','label' => ''.e(__('tournaments.starting_stack')).'','value' => ''.e(old('starting_stack', $defaults['starting_stack']['default'])).'','min' => ''.e($defaults['starting_stack']['min']).'','max' => ''.e($defaults['starting_stack']['max']).'','step' => ''.e($defaults['starting_stack']['step']).'','required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $attributes = $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $component = $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.number-input-with-slider','data' => ['id' => 'bigBlind','name' => 'big_blind','label' => ''.e(__('tournaments.big_blind')).'','value' => ''.e(old('big_blind', $defaults['big_blind']['default'])).'','min' => ''.e($defaults['big_blind']['min']).'','max' => ''.e($defaults['big_blind']['max']).'','step' => ''.e($defaults['big_blind']['step']).'','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('number-input-with-slider'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'bigBlind','name' => 'big_blind','label' => ''.e(__('tournaments.big_blind')).'','value' => ''.e(old('big_blind', $defaults['big_blind']['default'])).'','min' => ''.e($defaults['big_blind']['min']).'','max' => ''.e($defaults['big_blind']['max']).'','step' => ''.e($defaults['big_blind']['step']).'','required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $attributes = $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $component = $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>

        <div>
          <?php if (isset($component)) { $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.number-input-with-slider','data' => ['id' => 'totalPlayers','name' => 'num_players','label' => ''.e(__('tournaments.total_players')).'','value' => ''.e(old('num_players', $defaults['players']['default'])).'','min' => ''.e($defaults['players']['min']).'','max' => ''.e($defaults['players']['max']).'','step' => ''.e($defaults['players']['step']).'','required' => true,'class' => 'players-input']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('number-input-with-slider'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'totalPlayers','name' => 'num_players','label' => ''.e(__('tournaments.total_players')).'','value' => ''.e(old('num_players', $defaults['players']['default'])).'','min' => ''.e($defaults['players']['min']).'','max' => ''.e($defaults['players']['max']).'','step' => ''.e($defaults['players']['step']).'','required' => true,'class' => 'players-input']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $attributes = $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $component = $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
          <p id="players-error" style="color: #ef4444; display: none; margin-top: 0.5rem;" class="text-xs"><?php echo e(__('tournaments.players_error')); ?></p>
        </div>

        <!-- Row 2: Tournament Speed, Type -->
        <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['id' => 'speed','name' => 'speed','label' => ''.e(__('tournaments.speed')).'','options' => [
            'tipica' => __('tournaments.speed_typical'),
            'rapida' => __('tournaments.speed_fast'),
            'muy_rapida' => __('tournaments.speed_very_fast')
          ],'value' => ''.e(old('speed', 'tipica')).'','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'speed','name' => 'speed','label' => ''.e(__('tournaments.speed')).'','options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
            'tipica' => __('tournaments.speed_typical'),
            'rapida' => __('tournaments.speed_fast'),
            'muy_rapida' => __('tournaments.speed_very_fast')
          ]),'value' => ''.e(old('speed', 'tipica')).'','required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $attributes = $__attributesOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__attributesOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $component = $__componentOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__componentOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['id' => 'type','label' => ''.e(__('tournaments.type')).'','options' => ['mtt' => __('tournaments.type_normal')],'value' => 'mtt','disabled' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'type','label' => ''.e(__('tournaments.type')).'','options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['mtt' => __('tournaments.type_normal')]),'value' => 'mtt','disabled' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $attributes = $__attributesOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__attributesOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $component = $__componentOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__componentOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>

        <div>
          <label for="handsPerLevel" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
            <?php echo e(__('tournaments.hands_per_level')); ?>

          </label>
          <input
            type="number"
            id="handsPerLevel"
            name="hands_per_level"
            value="<?php echo e(old('hands_per_level', $blindLevelDefaults[old('speed', 'tipica')] ?? 10)); ?>"
            min="1"
            max="1000"
            required
            style="background-color: #0f1419; border: 1px solid #2e3a48; color: #ffffff;"
            class="w-full px-3 py-2 rounded focus:outline-none focus:border-blue-500 text-sm"
          >
          <p style="color: #6b7280;" class="text-xs mt-2"><?php echo e(__('tournaments.hands_per_level_help')); ?></p>
          <?php $__errorArgs = ['hands_per_level'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p style="color: #ef4444;" class="text-xs mt-2"><?php echo e($message); ?></p>
          <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div>
          <label for="anteSchedule" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
            <?php echo e(__('tournaments.ante_schedule')); ?>

          </label>
          <input
            type="text"
            id="anteSchedule"
            name="ante_schedule"
            value="<?php echo e(old('ante_schedule')); ?>"
            placeholder="0, 0, 10, 20"
            pattern="[0-9]+(\s*,\s*[0-9]+)*"
            style="background-color: #0f1419; border: 1px solid #2e3a48; color: #ffffff;"
            class="w-full px-3 py-2 rounded focus:outline-none focus:border-blue-500 text-sm"
          >
          <p style="color: #6b7280;" class="text-xs mt-2"><?php echo e(__('tournaments.ante_schedule_help')); ?></p>
          <?php $__errorArgs = ['ante_schedule'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p style="color: #ef4444;" class="text-xs mt-2"><?php echo e($message); ?></p>
          <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
      </div>

      <!-- Checkboxes: Hero Player, Show All Cards, No HUD Data -->
      <div style="padding-top: 1.5rem; padding-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
        <label class="flex items-center gap-3 cursor-pointer p-3 rounded" style="background-color: #0f1419; border: 1px solid #2e3a48; width: fit-content;">
          <input type="checkbox" id="heroPlayer" name="hero_player" value="1" <?php echo e(old('hero_player', true) ? 'checked' : ''); ?> style="accent-color: #047857; width: 18px; height: 18px;">
          <span style="color: #a1a5b0;"><?php echo e(__('tournaments.hero_player')); ?></span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer p-3 rounded" style="background-color: #0f1419; border: 1px solid #2e3a48; width: fit-content;">
          <input type="checkbox" name="show_all_cards" value="1" <?php echo e(old('show_all_cards') ? 'checked' : ''); ?> style="accent-color: #047857; width: 18px; height: 18px;">
          <span style="color: #a1a5b0;"><?php echo e(__('tournaments.show_all_cards')); ?></span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer p-3 rounded" style="background-color: #0f1419; border: 1px solid #2e3a48; width: fit-content;">
          <input type="checkbox" name="no_hud_data" value="1" <?php echo e(old('no_hud_data') ? 'checked' : ''); ?> style="accent-color: #f59e0b; width: 18px; height: 18px;">
          <span style="color: #a1a5b0;">📊 Sin datos del rival (Solo estadísticas del torneo)</span>
        </label>
      </div>

      <!-- Distribución de Rivales con Sliders -->
      <div>
        <p style="color: #d1d5db;" class="block text-xs uppercase font-semibold mb-4"><?php echo e(__('tournaments.select_rivals')); ?></p>
        <div style="background-color: #0f1419; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1.5rem;">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php $__empty_1 = true; $__currentLoopData = $bots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <div class="bot-item" data-bot-id="<?php echo e($bot->id); ?>">
                <div class="mb-3">
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                      <p style="color: #d1d5db;" class="font-semibold text-sm"><?php echo e($bot->nombre); ?></p>
                      <p style="color: #9ca3af;" class="text-xs"><?php echo e(ucfirst($bot->tipo)); ?></p>
                    </div>
                    <?php if (isset($component)) { $__componentOriginal1d05a77a3c60329ed21b9bef53e5a825 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1d05a77a3c60329ed21b9bef53e5a825 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bot-stats','data' => ['stats' => $bot->stats]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bot-stats'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['stats' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bot->stats)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1d05a77a3c60329ed21b9bef53e5a825)): ?>
<?php $attributes = $__attributesOriginal1d05a77a3c60329ed21b9bef53e5a825; ?>
<?php unset($__attributesOriginal1d05a77a3c60329ed21b9bef53e5a825); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1d05a77a3c60329ed21b9bef53e5a825)): ?>
<?php $component = $__componentOriginal1d05a77a3c60329ed21b9bef53e5a825; ?>
<?php unset($__componentOriginal1d05a77a3c60329ed21b9bef53e5a825); ?>
<?php endif; ?>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="bot-percentage-<?php echo e($bot->id); ?>" class="sr-only"><?php echo e(__('tournaments.total_percentage')); ?>: <?php echo e($bot->nombre); ?></label>
                  <input id="bot-percentage-<?php echo e($bot->id); ?>" type="range" name="bot_percentages[<?php echo e($bot->id); ?>]" min="0" max="100" value="<?php echo e(old('bot_percentages.' . $bot->id, 0)); ?>" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer bot-slider" style="accent-color: #047857;" data-bot-id="<?php echo e($bot->id); ?>">
                </div>

                <div class="flex items-center justify-between mb-2">
                  <span style="color: #9ca3af;" class="text-xs"><?php echo e(__('tournaments.total_percentage')); ?>:</span>
                  <span style="color: #10b981;" class="font-bold text-sm bot-percentage">0%</span>
                </div>

                <div class="flex items-center justify-between" style="background-color: #111820; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #2e3a48;">
                  <span style="color: #9ca3af;" class="text-xs"><?php echo e(__('tournaments.bots_to_create')); ?>:</span>
                  <span style="color: #3b82f6;" class="font-bold text-sm bot-count">0</span>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <p style="color: #9ca3af;" class="py-4 px-3 text-center col-span-3"><?php echo e(__('tournaments.no_bots')); ?></p>
            <?php endif; ?>
          </div>

          <!-- Resumen de totales -->
          <div class="mt-6 border-t border-slate-700" style="padding-top: 1.5rem;">
            <div class="grid grid-cols-2 gap-4">
              <div style="background-color: #111820; padding: 1rem; border-radius: 0.5rem; border: 1px solid #2e3a48;">
                <p style="color: #9ca3af;" class="text-xs mb-1"><?php echo e(__('tournaments.total_percentage')); ?></p>
                <p style="color: #10b981;" class="font-bold text-lg total-percentage">0%</p>
              </div>
              <div style="background-color: #111820; padding: 1rem; border-radius: 0.5rem; border: 1px solid #2e3a48;">
                <p style="color: #9ca3af;" class="text-xs mb-1"><?php echo e(__('tournaments.total_bots_to_create')); ?></p>
                <p style="color: #3b82f6;" class="font-bold text-lg total-bots">0</p>
              </div>
            </div>
            <p id="error-msg" style="color: #ef4444; display: none;" class="text-xs mt-3"><?php echo e(__('tournaments.percentage_error')); ?></p>
          </div>
        </div>
      </div>

      <!-- Player Time Configuration -->
      <div>
        <p style="color: #d1d5db;" class="block text-xs uppercase font-semibold mb-4">⏱️ <?php echo e(__('tournaments.player_time_config')); ?></p>
        <div style="background-color: #0f1419; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1.5rem;">
          <!-- Time Mode Selection -->
          <div class="mb-6">
            <p style="color: #9ca3af;" class="text-sm mb-3"><?php echo e(__('tournaments.time_mode')); ?></p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
              <label class="flex items-center gap-2 cursor-pointer" style="flex: 1; min-width: 200px;">
                <input type="radio" name="player_time_mode" value="unlimited" <?php echo e(old('player_time_mode', 'unlimited') === 'unlimited' ? 'checked' : ''); ?> style="accent-color: #047857; width: 18px; height: 18px;">
                <span style="color: #d1d5db;">🕐 <?php echo e(__('tournaments.time_unlimited')); ?></span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer" style="flex: 1; min-width: 200px;">
                <input type="radio" name="player_time_mode" value="standard" <?php echo e(old('player_time_mode') === 'standard' ? 'checked' : ''); ?> style="accent-color: #047857; width: 18px; height: 18px;">
                <span style="color: #d1d5db;">⏳ <?php echo e(__('tournaments.time_standard')); ?></span>
              </label>
            </div>
          </div>

          <!-- Standard Time Options (shown when standard is selected) -->
          <div id="standard-time-options" style="display: <?php echo e(old('player_time_mode', 'unlimited') === 'standard' ? 'block' : 'none'); ?>; border-top: 1px solid #2e3a48; padding-top: 1.5rem;">
            <div class="grid gap-6" style="grid-template-columns: repeat(2, 1fr);">
              <?php if (isset($component)) { $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.number-input-with-slider','data' => ['id' => 'decisionTime','name' => 'time_per_decision_seconds','label' => ''.e(__('tournaments.time_per_decision')).'','value' => ''.e(old('time_per_decision_seconds', 30)).'','min' => '5','max' => '300','step' => '5','suffix' => ''.e(__('tournaments.seconds')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('number-input-with-slider'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'decisionTime','name' => 'time_per_decision_seconds','label' => ''.e(__('tournaments.time_per_decision')).'','value' => ''.e(old('time_per_decision_seconds', 30)).'','min' => '5','max' => '300','step' => '5','suffix' => ''.e(__('tournaments.seconds')).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $attributes = $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $component = $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>

              <?php if (isset($component)) { $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.number-input-with-slider','data' => ['id' => 'bankTime','name' => 'bank_time_seconds','label' => ''.e(__('tournaments.bank_time')).'','value' => ''.e(old('bank_time_seconds', 300)).'','min' => '30','max' => '3600','step' => '30','suffix' => ''.e(__('tournaments.seconds')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('number-input-with-slider'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'bankTime','name' => 'bank_time_seconds','label' => ''.e(__('tournaments.bank_time')).'','value' => ''.e(old('bank_time_seconds', 300)).'','min' => '30','max' => '3600','step' => '30','suffix' => ''.e(__('tournaments.seconds')).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $attributes = $__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__attributesOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0)): ?>
<?php $component = $__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0; ?>
<?php unset($__componentOriginal242e845c8cc3bf058a663b8e8c7bc4e0); ?>
<?php endif; ?>
            </div>
            <p style="color: #6b7280; font-size: 0.875rem; margin-top: 1rem;">
              💡 <?php echo e(__('tournaments.time_info')); ?>

            </p>
          </div>
        </div>
      </div>

      <script>
        function updatePercentages() {
          const sliders = document.querySelectorAll('.bot-slider');
          let total = 0;
          let totalBots = 0;

          // Obtener el número de jugadores ingresado por el usuario
          const totalPlayersField = document.querySelector('#totalPlayers');
          const numPlayersInput = parseInt(totalPlayersField.value) || 0;
          const heroPlayerCheckbox = document.querySelector('#heroPlayer');
          const hasHeroPlayer = heroPlayerCheckbox?.checked ?? true;

          // Calcular máximo de bots permitidos
          const maxBotsAllowed = hasHeroPlayer ? numPlayersInput - 1 : numPlayersInput;

          // Primero, calcular el total actual
          sliders.forEach(slider => {
            const value = parseInt(slider.value) || 0;
            total += value;
          });

          // Ahora actualizar cada slider con sus límites dinámicos
          sliders.forEach(slider => {
            const value = parseInt(slider.value) || 0;

            // Calcular porcentaje disponible para este slider
            const otherPercentages = total - value;
            const maxForThisSlider = Math.max(0, 100 - otherPercentages);

            // Actualizar el atributo max del slider
            slider.max = maxForThisSlider;

            const botItem = slider.closest('.bot-item');
            const percentageElement = botItem.querySelector('.bot-percentage');
            const countElement = botItem.querySelector('.bot-count');

            if (percentageElement) {
              percentageElement.textContent = value + '%';
            }

            // Calcular bots a crear para este tipo basado en el número de jugadores ingresado
            const botsForThisType = Math.round(maxBotsAllowed * value / 100);

            if (countElement) {
              countElement.textContent = botsForThisType;
            }

            totalBots += botsForThisType;
          });

          // Actualizar totales
          const totalPercentage = document.querySelector('.total-percentage');
          if (totalPercentage) {
            totalPercentage.textContent = total + '%';
          }

          const totalBotsElement = document.querySelector('.total-bots');
          if (totalBotsElement) {
            totalBotsElement.textContent = totalBots;
          }

          // Mostrar error si el total excede 100%
          const errorMsg = document.querySelector('#error-msg');
          if (total > 100) {
            errorMsg.style.display = 'block';
            totalPercentage.style.color = '#ef4444';
          } else {
            errorMsg.style.display = 'none';
            totalPercentage.style.color = '#10b981';
          }

          // Validar que los bots no superen el número de jugadores
          const playersErrorMsg = document.querySelector('#players-error');

          if (totalBots > maxBotsAllowed) {
            playersErrorMsg.style.display = 'block';
            totalPlayersField.style.borderColor = '#ef4444';
          } else {
            playersErrorMsg.style.display = 'none';
            totalPlayersField.style.borderColor = '#2e3a48';
          }
        }

        // Sincronizar inputs y sliders
        function syncInputsAndSliders() {
          const fields = [
            { input: '#startingStack', slider: '#startingStackSlider' },
            { input: '#bigBlind', slider: '#bigBlindSlider' },
            { input: '#totalPlayers', slider: '#totalPlayersSlider' }
          ];

          fields.forEach(({ input, slider }) => {
            const inputEl = document.querySelector(input);
            const sliderEl = document.querySelector(slider);

            if (inputEl && sliderEl) {
              // Input → Slider
              inputEl.addEventListener('input', () => {
                sliderEl.value = inputEl.value;
                if (input === '#totalPlayers') {
                  updatePercentages();
                }
              });

              // Slider → Input
              sliderEl.addEventListener('input', () => {
                inputEl.value = sliderEl.value;
                if (input === '#totalPlayers') {
                  updatePercentages();
                }
              });
            }
          });
        }

        // Asegurarse de que los elementos existen antes de agregar listeners
        setTimeout(() => {
          syncInputsAndSliders();

          document.querySelectorAll('.bot-slider').forEach(slider => {
            slider.addEventListener('input', updatePercentages);
          });

          // Actualizar cuando se cambia el checkbox de hero player
          const heroPlayerCheckbox = document.querySelector('#heroPlayer');
          if (heroPlayerCheckbox) {
            heroPlayerCheckbox.addEventListener('change', () => {
              updatePercentages();
            });
          }

          updatePercentages();

          // Handle player time mode toggle
          const timeModeRadios = document.querySelectorAll('input[name="player_time_mode"]');
          const standardTimeOptions = document.getElementById('standard-time-options');
          const speedSelect = document.querySelector('#speed');
          const handsPerLevelInput = document.querySelector('#handsPerLevel');
          const handsPerLevelDefaults = <?php echo json_encode($blindLevelDefaults, 15, 512) ?>;

          speedSelect.addEventListener('change', () => {
            handsPerLevelInput.value = handsPerLevelDefaults[speedSelect.value] ?? handsPerLevelDefaults.tipica;
          });

          timeModeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
              if (radio.value === 'standard') {
                standardTimeOptions.style.display = 'block';
              } else {
                standardTimeOptions.style.display = 'none';
              }
            });
          });
        }, 100);
      </script>

      <!-- Available Hand Types -->
      <div style="margin-top: 2rem; margin-bottom: 2rem;">
        <p style="color: #d1d5db;" class="block text-xs uppercase font-semibold mb-4"><?php echo e(__('tournaments.hand_types')); ?></p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
          <?php $__currentLoopData = $handTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <label class="flex items-center gap-2 p-3 rounded cursor-pointer hover:bg-slate-800 transition" style="background-color: transparent; border: none;">
              <input type="checkbox" name="hand_types[]" value="<?php echo e($type['id']); ?>" <?php echo e(in_array($type['id'], old('hand_types', [])) ? 'checked' : ''); ?> style="accent-color: #047857; width: 18px; height: 18px;">
              <div class="flex-1 flex items-center gap-2">
                <span style="color: #d1d5db;" class="text-sm"><?php echo e($type['label']); ?></span>
                <?php if(!empty($type['example'])): ?>
                  <div class="flex gap-1">
                    <?php $__currentLoopData = $type['example']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visual): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <?php $__currentLoopData = $visual['cards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span style="background-color: #047857; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; min-width: 28px; text-align: center;"><?php echo e($card); ?></span>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                      <?php if($visual['separator'] ?? false): ?>
                        <span style="color: #047857; padding: 0 2px; font-weight: 600;">|</span>
                      <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </div>
                <?php endif; ?>
              </div>
            </label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php $__errorArgs = ['hand_types'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <p style="color: #ef4444;" class="text-xs mt-2"><?php echo e($message); ?></p>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <!-- Botón Iniciar -->
      <button type="submit" class="w-full py-4 rounded font-semibold text-white transition text-lg hover:opacity-90" style="background-color: #047857;">
        <?php echo e(__('tournaments.start_tournament')); ?>

      </button>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/mtt-simulator/create.blade.php ENDPATH**/ ?>