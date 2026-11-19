<#
.SYNOPSIS
  Instala y arranca Poker Trainer MTT con UN solo comando (Windows).

.DESCRIPTION
  Hace todo el trabajo: comprueba Docker, prepara una clave de aplicacion valida, construye y levanta los
  servicios, espera a que la aplicacion este lista y abre el navegador. La base de datos (migraciones y datos
  base) la prepara sola la aplicacion al arrancar.

  Se puede volver a ejecutar sin riesgo: no borra datos ni vuelve a sembrar nada.

.EXAMPLE
  .\init.ps1

.EXAMPLE
  .\init.ps1 -NoBrowser
#>
param(
    [switch]$NoBrowser,
    [int]$TimeoutSeconds = 300,
    [string]$Model = ''   # id del modelo de IA (p. ej. qwen3:8b); sin el, se pregunta o se usa el recomendado
)

# Docker escribe avisos y progreso por stderr: con 'Stop' PowerShell 5.1 los trata como errores. Se decide por el codigo de salida.
$ErrorActionPreference = 'Continue'
Set-Location -LiteralPath $PSScriptRoot

$Url = 'http://localhost:8001'

function Write-Step([string]$Text) { Write-Host "`n>> $Text" -ForegroundColor Cyan }
function Stop-Install([string]$Text) { Write-Host "`nERROR: $Text" -ForegroundColor Red; exit 1 }

# Una APP_KEY valida de Laravel (AES-256) es "base64:" + 32 bytes en base64 (44 caracteres)
function Test-AppKey([string]$Key) { return $Key -match '^base64:[A-Za-z0-9+/]{43}=$' }

function New-AppKey {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return 'base64:' + [Convert]::ToBase64String($bytes)
}

# Deja en el .env de la raiz una APP_KEY valida. Si ya hay una valida no la toca (cambiarla invalidaria sesiones y datos
# cifrados); si falta o es un texto de ejemplo (como "base64:YOUR_KEY_HERE") la sustituye: con esa clave la app no arranca.
function Set-AppKey([string]$EnvPath) {
    $lines = @()
    if (Test-Path -LiteralPath $EnvPath) { $lines = @(Get-Content -LiteralPath $EnvPath) }

    $index = -1
    for ($i = 0; $i -lt $lines.Count; $i++) { if ($lines[$i] -match '^\s*APP_KEY=') { $index = $i; break } }
    if ($index -ge 0) {
        $current = ($lines[$index] -replace '^\s*APP_KEY=', '').Trim().Trim('"').Trim("'")
        if (Test-AppKey $current) { return 'valida' }
    }

    $new = 'APP_KEY=' + (New-AppKey)
    if ($index -ge 0) { $lines[$index] = $new } else { $lines += $new }
    [System.IO.File]::WriteAllLines($EnvPath, [string[]]$lines, (New-Object System.Text.UTF8Encoding($false)))
    return 'generada'
}

# Permite cargar solo las funciones (para pruebas) sin instalar nada
if ($env:TRAINER_MTT_INIT_LIBRARY_ONLY -eq '1') { return }

Write-Host 'Poker Trainer MTT - instalacion' -ForegroundColor Green

Write-Step 'Comprobando Docker'
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Stop-Install 'Docker no esta instalado. Instala Docker Desktop (https://www.docker.com/products/docker-desktop) y vuelve a ejecutar .\init.ps1'
}
& docker info *> $null
if ($LASTEXITCODE -ne 0) {
    Stop-Install 'Docker esta instalado pero no esta en marcha. Abre Docker Desktop, espera a que arranque y vuelve a ejecutar .\init.ps1'
}

Write-Step 'Comprobando el equipo para la IA de los bots'
. (Join-Path $PSScriptRoot 'scripts\ai-hardware.ps1')
$catalog = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'app\resources\ai\models.json') -Raw | ConvertFrom-Json
$hardware = Get-HardwareProfile $PSScriptRoot
$gpuText = if ($hardware.gpus.Count) { ($hardware.gpus | ForEach-Object { "$($_.name) ($($_.vram_gb) GB)" }) -join ', ' } else { 'sin tarjeta NVIDIA' }
Write-Host "Memoria: $($hardware.ram_gb) GB | Procesador: $($hardware.cpu_threads) hilos | Tarjeta: $gpuText | Disco libre: $($hardware.disk_free_gb) GB"
$usable = Get-UsableModels $catalog $hardware
$minimum = $catalog.models | Where-Object { $_.id -eq $catalog.minimum } | Select-Object -First 1
if (-not (Get-ModelFit $minimum $hardware)) {
    Stop-Install ("Este equipo no cumple los requisitos minimos de Poker Trainer MTT. El modelo de IA minimo ($($minimum.name)) necesita " +
        "$($minimum.ram_gb) GB de memoria (o $($minimum.vram_gb) GB en una tarjeta NVIDIA) y $($minimum.disk_gb) GB de disco libre.")
}
. (Join-Path $PSScriptRoot 'scripts\ai-ollama.ps1')
$chosen = Select-Model $usable $Model
if (-not $chosen) { Stop-Install "El modelo '$Model' no esta entre los que este equipo puede ejecutar: $(($usable | ForEach-Object { $_.id }) -join ', ')" }
Write-Host "Modelo elegido: $($chosen.name) ($(if ($chosen.runs_on -eq 'gpu') { 'tarjeta grafica' } else { 'procesador' }))"
$aiDir = Join-Path $PSScriptRoot 'app\storage\app\ai'
New-Item -ItemType Directory -Force -Path $aiDir | Out-Null
$hardware | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $aiDir 'hardware.json') -Encoding UTF8
[ordered]@{ model = $chosen.id; name = $chosen.name; runs_on = $chosen.runs_on } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $aiDir 'model.json') -Encoding UTF8

Write-Step 'Preparando la clave de la aplicacion'
$result = Set-AppKey (Join-Path $PSScriptRoot '.env')
Write-Host "Clave de aplicacion: $result"
$useGpu = ($hardware.gpus.Count -gt 0)
Set-ComposeFiles (Join-Path $PSScriptRoot '.env') $useGpu

Write-Step 'Construyendo y levantando los servicios (la primera vez tarda unos minutos)'
# reverb reutiliza la imagen de la aplicacion: hay que construirla antes de levantar nada
& docker compose build app python-service
if ($LASTEXITCODE -ne 0) { Stop-Install 'No se pudieron construir los servicios. Revisa el mensaje de Docker de arriba.' }
& docker compose up -d
if ($LASTEXITCODE -ne 0) { Stop-Install 'No se pudieron levantar los servicios. Revisa el mensaje de Docker de arriba.' }

# Con tarjeta NVIDIA, comprueba que el contenedor de IA la ve; si no (Docker sin acceso a la tarjeta), sigue con el procesador
if ($useGpu) {
    & docker compose exec -T ollama nvidia-smi -L *> $null
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'AVISO: Docker no da acceso a la tarjeta NVIDIA; el modelo se ejecutara en el procesador (mas lento).' -ForegroundColor Yellow
        $useGpu = $false
        Set-ComposeFiles (Join-Path $PSScriptRoot '.env') $false
        & docker compose up -d --force-recreate ollama
        $still = Get-UsableModels $catalog (@{ ram_gb = $hardware.ram_gb; disk_free_gb = $hardware.disk_free_gb; gpus = @() })
        if (-not ($still | Where-Object { $_.id -eq $chosen.id })) {
            Stop-Install "Sin la tarjeta grafica este equipo no puede ejecutar $($chosen.name). Elige otro modelo con -Model: $(($still | ForEach-Object { $_.id }) -join ', ')"
        }
    }
}

Write-Step "Descargando el modelo de IA $($chosen.name) dentro de Docker (la primera vez son varios GB)"
& docker compose exec -T ollama ollama pull $chosen.id
if ($LASTEXITCODE -ne 0) { Stop-Install "No se pudo descargar el modelo $($chosen.id). Comprueba la conexion y el espacio en disco." }

Write-Step 'Esperando a que la aplicacion este lista (migraciones y datos base la primera vez)'
$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
$ready = $false
while ((Get-Date) -lt $deadline) {
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) { $ready = $true; break }
    } catch { }
    Start-Sleep -Seconds 3
}
if (-not $ready) {
    Stop-Install "La aplicacion no respondio en $TimeoutSeconds segundos. Mira el registro con: docker compose logs app"
}

Write-Step 'Comprobando que la aplicacion llega al modelo de IA'
# Sin comillas dobles dentro: PowerShell 5.1 las pierde al pasarlas a un programa externo
$check = "import urllib.request; print(urllib.request.urlopen('http://localhost:5000/ai/health', timeout=10).read().decode())"
$health = & docker compose exec -T python-service python -c $check 2>$null
if ($LASTEXITCODE -eq 0 -and $health -match [regex]::Escape($chosen.id)) { Write-Host "IA lista: $($chosen.name)" }
else { Write-Host "AVISO: el servicio de IA no llega a Ollama ($health). Los bots seguiran con reglas hasta que se arregle." -ForegroundColor Yellow }

Write-Host "`nListo. Poker Trainer MTT esta en $Url" -ForegroundColor Green
Write-Host 'Para detenerlo: docker compose down   |   Para volver a arrancarlo: .\init.ps1'
if (-not $NoBrowser) { Start-Process $Url }
exit 0
