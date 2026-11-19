<#
  Eleccion del modelo de IA y su descarga. Lo usa init.ps1.
  Todo corre dentro de Docker (servicio "ollama"): en el equipo no se instala nada.
#>

# El modelo que se propone por defecto: el mas capaz que cabe en la tarjeta; si ninguno, el mas capaz que cabe en memoria
function Get-RecommendedModel($Usable) {
    $gpu = @($Usable | Where-Object { $_.runs_on -eq 'gpu' })
    if ($gpu.Count) { return $gpu[0] }
    return $Usable[0]
}

# -Requested: id pedido por parametro (sin preguntar). Sin el, pregunta si hay una persona delante; si no, usa el recomendado.
function Select-Model($Usable, [string]$Requested) {
    if ($Requested) {
        return ($Usable | Where-Object { $_.id -eq $Requested } | Select-Object -First 1)
    }
    $recommended = Get-RecommendedModel $Usable
    if (-not [Environment]::UserInteractive -or [Console]::IsInputRedirected) { return $recommended }

    Write-Host 'Elige el modelo de IA (mas grande = decisiones mas finas, pero mas disco y mas tiempo):'
    for ($i = 0; $i -lt $Usable.Count; $i++) {
        $m = $Usable[$i]
        $mark = if ($m.id -eq $recommended.id) { '  <- recomendado' } else { '' }
        Write-Host ("  {0}) {1}  [{2}]{3}" -f ($i + 1), $m.name, $(if ($m.runs_on -eq 'gpu') { 'tarjeta grafica' } else { 'procesador, mas lento' }), $mark)
    }
    $answer = Read-Host "Numero (Enter = $($recommended.name))"
    if ($answer -match '^\d+$' -and [int]$answer -ge 1 -and [int]$answer -le $Usable.Count) { return $Usable[[int]$answer - 1] }
    return $recommended
}

# Pone COMPOSE_FILE en el .env: con tarjeta NVIDIA se suma docker-compose.gpu.yml (asi todos los "docker compose" posteriores lo usan)
function Set-ComposeFiles([string]$EnvPath, [bool]$Gpu) {
    $value = if ($Gpu) { 'docker-compose.yml;docker-compose.gpu.yml' } else { 'docker-compose.yml' }
    $lines = @(); if (Test-Path -LiteralPath $EnvPath) { $lines = @(Get-Content -LiteralPath $EnvPath) }
    $lines = @($lines | Where-Object { $_ -notmatch '^\s*COMPOSE_(FILE|PATH_SEPARATOR)=' })
    $lines += 'COMPOSE_PATH_SEPARATOR=;'
    $lines += "COMPOSE_FILE=$value"
    [System.IO.File]::WriteAllLines($EnvPath, [string[]]$lines, (New-Object System.Text.UTF8Encoding($false)))
}
