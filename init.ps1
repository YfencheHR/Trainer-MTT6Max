# Script para inicializar el proyecto Trainer-MTT

Write-Host "🚀 Iniciando Trainer-MTT..." -ForegroundColor Cyan

# 1. Crear Laravel project si no existe
if (!(Test-Path "app/artisan")) {
    Write-Host "📦 Creando proyecto Laravel..." -ForegroundColor Yellow
    docker run --rm -v "${PWD}/app:/app" composer create-project laravel/laravel . --prefer-dist --no-interaction
}

# 2. Build images
Write-Host "🔨 Building Docker images..." -ForegroundColor Yellow
docker-compose build

# 3. Start services
Write-Host "🐳 Levantando servicios..." -ForegroundColor Yellow
docker-compose up -d

# 4. Wait for services
Write-Host "⏳ Esperando que los servicios estén listos..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

# 5. Laravel setup
Write-Host "⚙️ Configurando Laravel..." -ForegroundColor Yellow
docker-compose exec -T app php artisan migrate --force

# 6. Verificar estado
Write-Host "`n✅ Status de servicios:" -ForegroundColor Green
docker-compose ps

Write-Host "`n✅ Proyecto iniciado exitosamente!" -ForegroundColor Green
Write-Host "📍 Accede a: http://localhost" -ForegroundColor Cyan
Write-Host "📍 API: http://localhost/api" -ForegroundColor Cyan
Write-Host "📍 Python IA: http://localhost/api/ai/" -ForegroundColor Cyan
