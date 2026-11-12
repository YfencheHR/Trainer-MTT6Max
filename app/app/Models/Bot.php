<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        'perfil',
        'vpip',
        'pfr',
        'tilt_risk',
        'profesional',
        'version_rango',
        'descripcion',
        'icono',
        'estado_juego',
        'metadata',
        'brain',
        'brain_version',
    ];

    protected $casts = [
        'estado_juego' => 'json',
        'metadata' => 'json',
        'brain' => 'array',
        'profesional' => 'boolean',
        'vpip' => 'integer',
        'pfr' => 'integer',
        'tilt_risk' => 'integer'
    ];

    /**
     * Relación a estadísticas HUD
     */
    public function hudStats(): HasMany
    {
        return $this->hasMany(BotHudStat::class);
    }

    /**
     * Relación a rangos
     */
    public function ranges(): HasMany
    {
        return $this->hasMany(BotRange::class);
    }

    public function preflopHandStats(): HasMany
    {
        return $this->hasMany(BotPreflopHandStat::class, 'bot_id');
    }

    public function preflopHandRanges(): HasMany
    {
        return $this->hasMany(BotPreflopHandRange::class, 'bot_id');
    }

    /**
     * Relación a estados del juego
     */
    public function gameStates(): HasMany
    {
        return $this->hasMany(BotGameState::class);
    }

    /**
     * Obtener estadísticas para un contexto específico
     */
    /** Tipo cuyo estilo y cerebro usa este bot: el suyo, o el del perfil primario del que es copia (los bots Pool). */
    public function templateTipo(): string
    {
        return (string) (((array) $this->metadata)['template'] ?? $this->tipo);
    }

    /** ¿Es un bot creado por la importación de PokerTracker (copia de un primario o perfil nuevo)? */
    public function isPool(): bool
    {
        return in_array(((array) $this->metadata)['source'] ?? null, ['pt4_pool', 'pt4_import'], true);
    }

    /**
     * Icono del rival: el guardado en la base de datos; si no tiene, el de su tipo (o el de su perfil), para que ningún
     * rival se quede sin icono.
     */
    public function getIconoAttribute($value): string
    {
        return $value ?: \App\Config\IconConfig::forTipo($this->tipo, $this->perfil);
    }

    /** Fila general de estadísticas (todas las calles), la que llenan el seeder, el editor y el importador. */
    public function generalStats(): ?BotHudStat
    {
        return $this->hudStats()->where('nivel', 1)->whereNull('posicion')->whereNull('accion')->whereNull('rango_ciegas')->first();
    }

    /**
     * Estadísticas por posición (apertura, 3-bet, call en frío, fold a 3-bet, 4-bet, squeeze), en orden de mesa.
     * Salen de las filas por acción que llena la importación de PokerTracker.
     *
     * @return array<string, array> posición => [rfi, vpip, pfr, three_bet, cold_call, fold_to_3bet, four_bet, squeeze, hands]
     */
    public function positionStats(): array
    {
        $cols = ['rfi', 'vpip', 'pfr', 'three_bet', 'cold_call', 'fold_to_3bet', 'four_bet', 'squeeze'];
        $out = [];
        foreach ($this->hudStats()->whereNotNull('posicion')->whereNotNull('accion')->whereNull('rango_ciegas')->get() as $row) {
            foreach ($cols as $c) {
                if ($row->{$c} !== null) {
                    $out[$row->posicion][$c] = $row->{$c};
                }
            }
            $out[$row->posicion]['hands'] = max($out[$row->posicion]['hands'] ?? 0, (int) $row->sample_size);
        }

        $order = ['UTG', 'MP', 'CO', 'BTN', 'SB', 'BB'];
        uksort($out, fn($a, $b) => (array_search($a, $order) === false ? 9 : array_search($a, $order)) <=> (array_search($b, $order) === false ? 9 : array_search($b, $order)));

        return $out;
    }

    /**
     * Postflop por calle, en posición (ip) / fuera de posición (oop) y según el papel (agresor / defensor).
     *
     * @return array<string, array<string, array>> calle => ip|oop => [agg => [bet, n], def => [fold, call, raise, n]]
     */
    public function postflopRoles(): array
    {
        $out = [];
        $rows = $this->hudStats()->whereNull('posicion')->where('accion', 'like', '%\_agg\_%')->orWhere(fn($q) => $q->where('bot_id', $this->id)->whereNull('posicion')->where('accion', 'like', '%\_def\_%'))->get();
        foreach ($rows as $r) {
            if ($r->bot_id !== $this->id || !preg_match('/^(flop|turn|river)_(agg|def)_(ip|oop)$/', (string) $r->accion, $m)) {
                continue;
            }
            [, $street, $role, $ip] = $m;
            $out[$street][$ip][$role] = $role === 'agg'
                ? ['bet' => $r->cbet, 'n' => (int) $r->sample_size]
                : ['fold' => $r->f2cbet, 'call' => $r->call_vs_bet, 'raise' => $r->raise_vs_bet, 'n' => (int) $r->sample_size];
        }

        return $out;
    }

    public function getStats(int $nivel = 1, ?string $posicion = null, ?string $accion = null, ?string $rango_ciegas = null)
    {
        $query = $this->hudStats();

        if ($nivel) {
            $query->where('nivel', $nivel);
        }

        if ($posicion) {
            $query->where('posicion', $posicion);
        }

        if ($accion) {
            $query->where('accion', $accion);
        }

        if ($rango_ciegas) {
            $query->where('rango_ciegas', $rango_ciegas);
        }

        return $query->first();
    }

    /**
     * Obtener rango activo
     */
    public function getRango(?string $posicion = null, ?string $accion = null, ?string $rango_ciegas = null)
    {
        $query = $this->ranges()
            ->where('activo', true)
            ->latest('updated_at');

        if ($posicion) {
            $query->where('posicion', $posicion);
        }

        if ($accion) {
            $query->where('accion', $accion);
        }

        if ($rango_ciegas) {
            $query->where('rango_ciegas', $rango_ciegas);
        }

        return $query->first();
    }

    /**
     * Obtener estado actual del juego
     */
    public function getCurrentGameState()
    {
        return $this->gameStates()
            ->whereNull('updated_at')
            ->orWhere('updated_at', '>', now()->subHours(1))
            ->latest('updated_at')
            ->first();
    }
}
