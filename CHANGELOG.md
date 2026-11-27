# Cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Las versiones siguen [SemVer](https://semver.org/lang/es/); mientras sea `0.x` pueden cambiar cosas entre versiones.

## [Sin publicar]

### Corregido
- **Importación de PokerTracker**: la estadística de lo que hace cada perfil ante limpers solo contaba manos con cartas conocidas, y PokerTracker no guarda las de quien foldea, así que los bots casi nunca foldeaban ante limpers (1-2 % frente a ~70 % real). Quien ya haya importado debe **volver a importar** para corregirlo.

## [0.1.0-beta] - 2026-11-26

Primera versión pública para probar.

### Qué incluye
- **Simulador de torneos MTT 6-max**: mesa en tiempo real (Laravel Reverb), ciegas por manos, mesas secundarias resueltas por el servidor, eliminaciones y nivelación de mesas. Un torneo solo se juega desde una ventana a la vez.
- **Rivales a partir de PokerTracker 4** (`/import`): estadísticas generales, por posición, por tramo de stack y por tipo de bote; perfiles de bot generados con sus tablas preflop y la matriz de apuestas. Al terminar la importación se comprueba sola cuánto se parecen los perfiles a jugadores reales.
- **Cerebro de los bots**: tablas preflop por perfil (apertura, limp, aislar limpers, defensa de ciegas, respuesta a 3-bet) y reglas postflop con percepción según la habilidad; cada torneo juega con una copia congelada de sus rivales.
- **IA local opcional** (Ollama, desactivada por defecto): los bots pueden decidir preflop, flop, turn y river con un modelo de lenguaje; las herramientas calculan (equity, rangos, blockers, ventaja de rango) y la IA solo decide.
- **Simulador sin cabeza y auditoría** de las decisiones de los bots (`tournament:simulate`, `bots:audit-postflop`).
- **Historial del torneo** manos a mano, con resumen, ganador y EV de cada decisión.
- **Repetir una mano** con las mismas cartas y decisiones nuevas de los bots, verla en la mesa paso a paso y guardar las repeticiones para analizarlas.
- **Réplica de manos reales** (`bots:replay`, `bots:compare-engines`) para medir cuánto coinciden los bots con jugadores reales.

### Limitaciones conocidas
- Los bots coinciden con jugadores reales en torno a un 56-66 % de las decisiones según el método; la defensa de la ciega grande es algo ancha y la apertura desde BTN/SB algo frecuente.
- La IA local empata con las reglas en las pruebas actuales, no las mejora todavía, y exige un modelo grande para ir bien.
- Sin importar un historial de PokerTracker no hay rivales con los que jugar.
- No hay usuarios ni contraseña: es para usarse en tu propio equipo, no para exponerla a internet.
- El instalador de un comando (`init.ps1`) es solo para Windows; en Linux y macOS: `docker compose up -d --build` (aún sin probar a fondo).
