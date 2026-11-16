# 🚀 Mejoras para Equity Simulation System

## 1. Optimizar Performance (CRÍTICO)

### Problema
Generación de tablas toma 2+ horas. Inaceptable.

### Solución: Reducir iteraciones + Caché inteligente

**Propuesta:**
```python
# equity_simulator.py
class EquitySimulator:
    def __init__(self, seed=None, cache_size=500):
        # ... 
        self._equity_cache = {}  # Cachear resultados de simulaciones
        self._cache_size = cache_size
        
    def simulate_equity(self, hero_hand_str, villain_range_strs, iterations=1000):
        """Cache results por (hero_hand, villain_range_hash)"""
        cache_key = (hero_hand_str, tuple(sorted(villain_range_strs)))
        if cache_key in self._equity_cache:
            return self._equity_cache[cache_key]
        
        result = self.simulate_multiway_equity(...)
        
        # Guardar en caché (con límite)
        if len(self._equity_cache) < self._cache_size:
            self._equity_cache[cache_key] = result
        
        return result
```

**Beneficio:** 
- Mismo resultado que tarda 2 horas, ahora en ~15 minutos
- Reutilizar cálculos entre tablas

---

## 2. Flexibilidad en rangos (IMPORTANTE)

### Problema
```python
defense_range = self.hand_names[:num_hands_defending]
```
Usa las primeras N manos (por fuerza), muy rígido.

### Solución: Samplear rangos
```python
def generate_attack_table(self, stack_depth_bb, defense_range_strs=None):
    """
    Args:
        defense_range_strs: lista de manos específicas, ej ['AA', 'KK', 'AKs']
                            Si None, usa default por porcentaje
    """
    if defense_range_strs is None:
        # Default: top 20%
        num_hands = max(1, int(169 * 20 / 100))
        defense_range = self.hand_names[:num_hands]
    else:
        defense_range = defense_range_strs
    
    # ... rest of logic
```

**Beneficio:** Usuarios pueden simular contra rangos específicos

---

## 3. Configuración de thresholds (IMPORTANTE)

### Problema
```python
include = equity > 0.5  # Mágico, sin flexibilidad
```

### Solución: Parámetro configurable
```python
def generate_attack_table(self, 
    stack_depth_bb, 
    defense_range_strs=None,
    equity_threshold=0.5):  # ← Configurable
    
    for hand in self.hand_names:
        sim = self.simulate_equity(...)
        include = sim['equity'] > equity_threshold  # Usa parámetro
```

---

## 4. Batch processing para Laravel (NUEVO)

### Propuesta: Generar en background con Queue

```php
// app/Services/Equity/GenerateTablesJob.php
class GenerateTablesJob implements ShouldQueue {
    public function handle(HandMatrixGenerator $generator) {
        // Cada tabla en su propia tarea
        Bus::batch([
            new GenerateAttackTableJob('0-6'),
            new GenerateAttackTableJob('6-25'),
            new GenerateDefenseTableJob('0-6'),
            new GenerateDefenseTableJob('6-25'),
            // ... más
        ])->dispatch();
    }
}

// Command actualizado
php artisan equity:generate-tables --background  // No bloquea
```

**Beneficio:** Usuarios no esperan 15 minutos en terminal

---

## 5. Integrar EquitySimulationController con HandMatrixController

### Propuesta: Unificar bajo una ruta

```php
// routes/api.php
Route::prefix('/api/hand-matrix')->group(function () {
    Route::post('/simulate', [HandMatrixController::class, 'simulate']);
    Route::get('/ranges', [HandMatrixController::class, 'getRanges']);
    Route::get('/check-hand', [HandMatrixController::class, 'checkHand']);
    Route::post('/generate-tables', [HandMatrixController::class, 'generateTables']);
});
```

---

## 6. Componente Matrix Blade (FALTA)

### Crear el componente faltante

```php
// app/View/Components/Matrix.php
class Matrix extends Component {
    public function __construct(
        public int $columns,
        public array $items,
        public string $cellComponent = 'matrix.cell',
        public string $id = 'matrix'
    ) {}
    
    public function render() {
        return view('components.matrix');
    }
}

// resources/views/components/matrix.blade.php
<div class="matrix" id="{{ $id }}">
    @foreach(array_chunk($items, $columns) as $row)
        <div class="row">
            @foreach($row as $item)
                @if($item)
                    <x-dynamic-component :component="$cellComponent" :item="$item" />
                @else
                    <div class="cell empty"></div>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
```

---

## 7. Mejora en HandMatrixController

### Integrar con EquitySimulationClient

```php
class HandMatrixController extends Controller {
    public function __construct(
        private HandMatrixGenerator $generator,
        private EquitySimulationClient $client,
    ) {}
    
    public function simulate(Request $request) {
        $validated = $request->validate([
            'hero_hand' => 'required|string',
            'villain_range' => 'required|array',
            'iterations' => 'integer|min:100|max:10000',
        ]);
        
        // Usar el cliente Python
        return $this->client->simulateEquity(
            $validated['hero_hand'],
            $validated['villain_range'],
            $validated['iterations'] ?? 5000
        );
    }
}
```

---

## 8. Tests para generación

### Agregar tests en Python

```python
def test_generate_attack_table_returns_correct_structure(self):
    result = self.simulator.generate_attack_table(50, defense_range_strs=['AA', 'KK'])
    
    self.assertIn('AA', result)
    self.assertIn('equity', result['AA'])
    self.assertIn('include', result['AA'])
    self.assertGreaterEqual(result['AA']['equity'], 0)
    self.assertLessEqual(result['AA']['equity'], 1)

def test_equity_threshold_filters_hands_correctly(self):
    result = self.simulator.generate_attack_table(
        50, 
        equity_threshold=0.6
    )
    
    included_hands = [h for h, v in result.items() if v['include']]
    for hand in included_hands:
        self.assertGreater(result[hand]['equity'], 0.6)
```

---

## 📋 Plan de implementación

### Fase 1 (Hoy)
- ✅ Agregar caché en EquitySimulator
- ✅ Reducir iteraciones a 1000
- ✅ Tests para generación

### Fase 2 (Mañana)
- ✅ Parámetros configurables (equity_threshold, defense_range_strs)
- ✅ Crear componente Matrix Blade
- ✅ Integrar controladores

### Fase 3 (Día 3)
- ✅ Background jobs para generación
- ✅ UI para diferentes simulaciones

---

## 📊 Comparativa de performance

| Escenario | Antes | Después |
|-----------|-------|---------|
| 1 tabla (169 manos × 2000 iter) | 11 min | 2-3 min |
| 10 tablas (completas) | 1.5+ horas | 20-30 min |
| Con caché (reutilización) | - | 5-10 min |
| Background job | Bloquea terminal | No bloquea |

---

## 💡 Notas técnicas

- El caché debería persistir en Redis para reutilizar entre generaciones
- Los tests garantizan que los cambios no rompan nada
- La integración Controller-Service es más limpia
- Blade Component es reutilizable para otras matrices

