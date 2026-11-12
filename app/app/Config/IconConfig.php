<?php

namespace App\Config;

/**
 * Colección de iconos para los rivales (icons.json): grupos con nombre, empezando por los tipos de jugador de poker,
 * y los iconos por defecto de cada tipo y perfil. Para añadir un icono nuevo basta con añadirlo a un grupo del JSON.
 */
class IconConfig
{
    private static $config = null;

    private static function loadConfig(): array
    {
        if (self::$config === null) {
            self::$config = json_decode(file_get_contents(__DIR__ . '/icons.json'), true);
        }

        return self::$config;
    }

    /** Lista plana de iconos (compatibilidad). */
    public static function getIcons(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn($g) => array_column($g['icons'], 'i'), self::getGroups()))));
    }

    /** @return array<int, array{name: string, icons: array<int, array{i: string, n: string}>}> */
    public static function getGroups(): array
    {
        return self::loadConfig()['groups'];
    }

    /** Icono por defecto de un tipo de bot; si no hay, el del perfil; si no, el genérico. */
    public static function forTipo(?string $tipo, ?string $perfil = null): string
    {
        $d = self::loadConfig()['defaults'];

        return $d['tipos'][$tipo] ?? ($perfil ? ($d['perfiles'][$perfil] ?? null) : null) ?? $d['fallback'];
    }

    /**
     * Icono para un bot nuevo creado por la importación: el de su perfil y, si otro rival ya lo usa, el primer animal libre
     * (así los rivales se distinguen a simple vista).
     *
     * @param array<int, string> $used iconos que ya llevan otros rivales
     */
    public static function pick(?string $perfil, array $used): string
    {
        $first = self::forPerfil($perfil);
        if (!in_array($first, $used, true)) {
            return $first;
        }
        $animals = array_column(array_values(array_filter(self::getGroups(), fn($g) => $g['name'] === 'Animales'))[0]['icons'] ?? [], 'i');
        foreach ($animals as $icon) {
            if (!in_array($icon, $used, true)) {
                return $icon;
            }
        }

        return $first;
    }

    /** Icono por defecto de un perfil (para los bots creados por la importación). */
    public static function forPerfil(?string $perfil): string
    {
        $d = self::loadConfig()['defaults'];

        return $d['perfiles'][$perfil] ?? $d['fallback'];
    }
}
