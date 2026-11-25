<?php

namespace App\Services\Tournament;

use Illuminate\Support\Facades\Cache;

/**
 * Una sola ventana a la vez por torneo.
 *
 * Cada ventana (pestaña o navegador) que abre la mesa tiene un identificador propio y lo renueva cada pocos segundos mientras sigue abierta. Quien lo
 * tiene vigente es la ÚNICA ventana que puede jugar: el servidor rechaza las acciones de las demás (ver EnsureTournamentWindow). Otra ventana puede
 * quedarse con el torneo ("continuar aquí"); entonces la anterior deja de ser la activa y lo ve en su siguiente renovación.
 *
 * Si la ventana activa se cierra sin avisar (se cae el navegador), su permiso caduca solo a los pocos segundos y otra ventana puede entrar sin más.
 */
class TournamentWindowLock
{
    /** Segundos que dura el permiso de una ventana sin renovarse (la mesa lo renueva cada ~5 s). */
    public const TTL = 20;

    /**
     * Intenta que `$token` sea la ventana activa del torneo.
     *
     * @param  bool  $take  quedarse con el torneo aunque otra ventana lo tenga ("continuar aquí")
     * @return array{owner: bool, took: bool} owner: ¿es ahora la ventana activa?; took: ¿se la quitó a otra?
     */
    public function claim(int $tournamentId, string $token, bool $take = false): array
    {
        return Cache::lock($this->lockName($tournamentId), 3)->block(2, function () use ($tournamentId, $token, $take) {
            $current = $this->owner($tournamentId);
            if ($current === null || $current === $token || $take) {
                Cache::put($this->key($tournamentId), $token, self::TTL);

                return ['owner' => true, 'took' => $current !== null && $current !== $token];
            }

            return ['owner' => false, 'took' => false];
        });
    }

    /** La ventana activa (su identificador) o null si no hay ninguna vigente. */
    public function owner(int $tournamentId): ?string
    {
        $token = Cache::get($this->key($tournamentId));

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** ¿Puede jugar esta ventana? Sí si es la activa o si nadie tiene el torneo abierto. */
    public function allows(int $tournamentId, ?string $token): bool
    {
        $owner = $this->owner($tournamentId);

        return $owner === null || ($token !== null && hash_equals($owner, $token));
    }

    /** La ventana se cierra: libera el torneo solo si era la activa. */
    public function release(int $tournamentId, string $token): void
    {
        Cache::lock($this->lockName($tournamentId), 3)->block(2, function () use ($tournamentId, $token) {
            if ($this->owner($tournamentId) === $token) {
                Cache::forget($this->key($tournamentId));
            }
        });
    }

    private function key(int $tournamentId): string
    {
        return "tournament-window:{$tournamentId}";
    }

    private function lockName(int $tournamentId): string
    {
        return "tournament-window-lock:{$tournamentId}";
    }
}
