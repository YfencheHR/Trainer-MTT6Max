@props(['handId'])

<div x-data="botsActionRanges({{ $handId }})" class="bg-slate-900 rounded-lg border border-slate-700 p-4">
    <h3 class="text-sm font-semibold text-slate-200 mb-4">🎯 Rivales en Acción - Rangos</h3>

    <div x-show="activeBots.length === 0" class="text-slate-400 text-sm">
        Esperando acción de bots...
    </div>

    <div x-show="activeBots.length > 0" class="space-y-3">
        <template x-for="bot in activeBots" :key="bot.id">
            <div class="border border-slate-700 rounded p-2">
                <!-- Bot Header -->
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-100 text-sm" x-text="bot.name"></span>
                        <span class="text-xs bg-slate-700 px-1.5 py-0.5 rounded" x-text="bot.position"></span>
                    </div>
                    <div class="flex gap-1">
                        <span x-show="bot.action" class="text-xs bg-blue-900 text-blue-200 px-1.5 py-0.5 rounded" x-text="bot.action.toUpperCase()"></span>
                        <span x-show="bot.is_folded" class="text-xs bg-red-900 text-red-200 px-1.5 py-0.5 rounded">FOLD</span>
                        <span x-show="bot.is_all_in && !bot.is_folded" class="text-xs bg-orange-900 text-orange-200 px-1.5 py-0.5 rounded">ALL-IN</span>
                    </div>
                </div>

                <!-- Range Matrix -->
                <div x-show="bot.ranges && Object.keys(bot.ranges).length > 0" class="overflow-x-auto text-xs">
                    <table class="w-full">
                        <tbody>
                            <template x-for="(range, hand) in bot.ranges" :key="hand">
                                <tr class="border-b border-slate-800 hover:bg-slate-800" :class="{'opacity-40': bot.is_folded}">
                                    <td class="px-1 py-0.5 font-mono text-slate-300" x-text="hand"></td>
                                    <td class="text-center px-1 py-0.5">
                                        <span x-show="range.fold > 0" class="text-red-400 font-semibold">F</span>
                                        <span x-show="range.call > 0" class="text-yellow-400 font-semibold">C</span>
                                        <span x-show="range.raise > 0" class="text-green-400 font-semibold">R</span>
                                        <span x-show="range.check > 0" class="text-blue-400 font-semibold">K</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- No Ranges -->
                <div x-show="!bot.ranges || Object.keys(bot.ranges).length === 0" class="text-slate-400 text-xs py-1">
                    <span x-show="bot.action === 'fold'">Se fue preflop</span>
                    <span x-show="!bot.action || bot.action !== 'fold'">Esperando rango...</span>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function botsActionRanges(handId) {
    return {
        handId: handId,
        activeBots: [],

        init() {
            console.log('Init botsActionRanges para hand', this.handId);

            // Escuchar actualizaciones del gameState global
            this.$watch(() => window.gameState, (newState) => {
                if (newState && newState.hand_id === this.handId) {
                    this.updateBotsFromState(newState);
                }
            }, { deep: true, immediate: true });
        },

        updateBotsFromState(state) {
            console.log('updateBotsFromState:', state);

            if (!state || !state.players) return;

            const botsMap = new Map(this.activeBots.map(b => [b.id, b]));
            const newActiveBots = [];

            state.players.forEach(player => {
                // Solo bots
                if (!player.is_bot) return;

                const botData = {
                    id: player.id,
                    name: player.name,
                    position: player.position,
                    is_folded: player.is_folded,
                    is_all_in: player.is_all_in,
                    action: state.last_action?.player_id === player.id ? state.last_action.action : null,
                    ranges: null
                };

                // Si hay dynamic_ranges para este jugador
                if (state.dynamic_ranges && state.dynamic_ranges.player_id === player.id) {
                    botData.ranges = state.dynamic_ranges.ranges;
                } else {
                    // Mantener rangos anteriores si existe
                    const existingBot = botsMap.get(player.id);
                    if (existingBot && existingBot.ranges) {
                        botData.ranges = existingBot.ranges;
                    }
                }

                newActiveBots.push(botData);
            });

            this.activeBots = newActiveBots;
        }
    };
}
</script>
