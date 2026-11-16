# Inicialización de Base de Datos

## Opción 1: Comando automatizado (RECOMENDADO)

```bash
docker-compose exec app php artisan db:initialize
```

Este comando ejecuta automáticamente:
- Migraciones (`php artisan migrate --force`)
- Seeders (`php artisan db:seed --force`)

Y muestra un resumen de los datos cargados.

## Opción 2: Comandos manuales

```bash
# Ejecutar solo migraciones
docker-compose exec app php artisan migrate --force

# Ejecutar solo seeders
docker-compose exec app php artisan db:seed --force
```

## Qué se carga

Cuando ejecutas los seeders, se cargan automáticamente:
- **9 Bots** con estadísticas
- **15 Made Hands** (combinaciones de cartas)
- **Cards** (baraja completa)
- **Hand Groups** y matrices
- **Starting Hands** grupos
- Estrategias preflop

## Nuevas instancias del proyecto

Para un proyecto recién clonado:

```bash
# 1. Levantar contenedores
docker-compose up -d

# 2. Instalar dependencias PHP
docker-compose exec app composer install

# 3. Inicializar la base de datos
docker-compose exec app php artisan db:initialize
```

¡Listo! El proyecto está completamente inicializado.
