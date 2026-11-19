<#
  Comprobacion del equipo para la IA de los bots (la usa init.ps1 antes de construir nada).
  Mide memoria, procesador, tarjeta grafica NVIDIA y disco libre, y los cruza con el catalogo de modelos
  (app/resources/ai/models.json). La misma regla la aplica App\Services\Ai\ModelCatalog en la aplicacion.
#>

function Get-HardwareProfile([string]$Path) {
    $ram = [math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory / 1GB, 1)
    $threads = [int](Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum
    $drive = (Get-Item -LiteralPath $Path).PSDrive
    $disk = [math]::Round((Get-PSDrive -Name $drive.Name).Free / 1GB, 1)

    # Solo tarjetas NVIDIA (nvidia-smi da la memoria real; Win32_VideoController se queda en 4 GB). Otras tarjetas se usan como procesador.
    $gpus = @()
    $smi = Get-Command nvidia-smi -ErrorAction SilentlyContinue
    if ($smi) {
        foreach ($line in (& nvidia-smi --query-gpu=name,memory.total --format=csv,noheader,nounits 2>$null)) {
            $parts = $line -split ','
            if ($parts.Count -ge 2) { $gpus += [ordered]@{ name = $parts[0].Trim(); vram_gb = [math]::Round([double]$parts[1].Trim() / 1024, 1) } }
        }
    }

    return [ordered]@{ ram_gb = $ram; disk_free_gb = $disk; cpu_threads = $threads; gpus = @($gpus) }
}

# 'gpu', 'cpu' o $null (misma regla que ModelCatalog::fit)
function Get-ModelFit($Model, $Hardware) {
    if ($Hardware.disk_free_gb -lt $Model.disk_gb) { return $null }
    $bestVram = 0
    foreach ($g in $Hardware.gpus) { if ($g.vram_gb -gt $bestVram) { $bestVram = $g.vram_gb } }
    if ($bestVram -ge $Model.vram_gb) { return 'gpu' }
    if ($Hardware.ram_gb -ge $Model.ram_gb) { return 'cpu' }
    return $null
}

function Get-UsableModels($Catalog, $Hardware) {
    $out = @()
    foreach ($m in $Catalog.models) {
        $fit = Get-ModelFit $m $Hardware
        if ($fit) { $out += [pscustomobject]@{ id = $m.id; name = $m.name; params_b = $m.params_b; runs_on = $fit } }
    }
    return @($out | Sort-Object -Property @{ Expression = 'params_b'; Descending = $true }, @{ Expression = 'runs_on'; Descending = $false })
}
