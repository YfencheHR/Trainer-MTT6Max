# 📊 Componente Matrix - Guía de uso

## ¿Qué es?

Componente Blade reutilizable para renderizar matrices de 13×13 (típicas en poker para 169 combinaciones de manos). Automáticamente:
- Divide items en filas
- Renderiza celdas vacías
- Aplica estilos responsive
- Soporta colores por tipo de mano

---

## Instalación

✅ Ya instalado:
- `app/View/Components/Matrix.php` - Componente
- `resources/views/components/matrix.blade.php` - Vista principal
- `resources/views/components/matrix/hand-cell.blade.php` - Celda de mano

---

## Uso básico

```blade
<x-matrix
  :items="$hands"
  :columns="13"
  cellComponent="matrix.hand-cell"
  id="poker-matrix"
/>
```

---

## Propiedades

| Propiedad | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `items` | array | `[]` | Items a renderizar |
| `columns` | int | `13` | Número de columnas |
| `cellComponent` | string | `matrix.cell` | Componente para celdas |
| `id` | string | `matrix` | ID HTML |
| `class` | string | `''` | Clases CSS adicionales |

---

## Estructura de items

Cada item debe tener:

```php
[
    'name' => 'AKs',           // Nombre (mostrado en celda)
    'gto_ev' => 2.34,          // EV (opcional, muestra valor)
    'bgColor' => '#047857',    // Color de fondo (opcional)
    // ... otros campos
]
```

---

## Ejemplo completo

```blade
@php
  $items = [];
  $ranks = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2'];
  
  // Pares
  foreach($ranks as $rank) {
    $items[] = [
      'name' => $rank . $rank,
      'gto_ev' => rand(1, 5),
      'bgColor' => '#047857'
    ];
  }
  
  // No pares
  foreach($ranks as $i => $r1) {
    foreach(array_slice($ranks, $i+1) as $r2) {
      $items[] = [
        'name' => $r1 . $r2 . 's',
        'gto_ev' => rand(1, 5) / 2,
        'bgColor' => '#1e40af'
      ];
      $items[] = [
        'name' => $r1 . $r2 . 'o',
        'gto_ev' => rand(1, 5) / 2,
        'bgColor' => '#92400e'
      ];
    }
  }
@endphp

<x-matrix :items="$items" :columns="13" />
```

---

## Subcomponente: hand-cell

Renderiza cada celda de mano. Propiedades recibidas:

```php
$item           // El item del array
$rowIndex       // Índice de fila (0-12)
$cellIndex      // Índice dentro de la fila (0-12)
$globalIndex    // Índice global (0-168)
```

Personalización:

```blade
{{-- Crear tu propia celda --}}
<div class="custom-cell" data-hand="{{ $item['name'] }}">
  {{ $item['name'] }}
  @if($item['gto_ev'] > 2)
    <span class="badge-green">STRONG</span>
  @endif
</div>
```

---

## Estilos CSS

### Variables disponibles:
```css
--matrix-border: #2e3a48  /* Color de borde */
```

### Clases principales:
```css
.matrix-container        /* Contenedor principal */
.matrix-wrapper         /* Wrapper interno */
.matrix-row             /* Cada fila (13 celdas) */
.matrix-cell            /* Cada celda */
.matrix-cell.empty      /* Celda vacía */
.matrix-cell.selected   /* Celda seleccionada */
```

### Customizar:

```blade
<x-matrix
  :items="$items"
  class="custom-theme"
/>

<style>
  .custom-theme {
    --matrix-border: #ff6b6b;
  }
  
  .custom-theme .matrix-cell {
    font-size: 1rem;
    padding: 0.5rem;
  }
</style>
```

---

## Responsivo

Automáticamente:
- **Desktop** (>768px): 50px celdas, tamaño normal
- **Tablet** (768px): 30px celdas, fuente reducida
- **Mobile** (<480px): 24px celdas, ultra compacto

---

## Integración con Alpine.js

```blade
<x-matrix
  :items="$items"
  @click="handleCellClick"
/>

<script>
export default {
  handleCellClick(event) {
    const hand = event.target.closest('[data-hand]')?.dataset.hand;
    console.log('Selected:', hand);
  }
}
</script>
```

---

## Métodos del componente (PHP)

```php
$matrix->rows()              // Divide items en filas
$matrix->rowCount()          // Número de filas
$matrix->getItemIndex($r, $c) // Índice global de fila/celda
```

---

## Ejemplo: Matriz interactiva con Alpine

```blade
<div x-data="matrixController()">
  <x-matrix
    :items="$hands"
    id="hands-matrix"
    @click="selectHand($event)"
  />
  
  <div x-show="selected">
    <p>Seleccionada: <strong x-text="selected.name"></strong></p>
    <p>EV: <span x-text="selected.gto_ev"></span></p>
  </div>
</div>

<script>
function matrixController() {
  return {
    selected: null,
    selectHand(event) {
      const cell = event.target.closest('[data-hand]');
      if (!cell) return;
      this.selected = {
        name: cell.dataset.hand,
        gto_ev: parseFloat(cell.dataset.ev || 0)
      };
    }
  }
}
</script>
```

---

## Troubleshooting

**❌ Celdas vacías al final**
- Normal. Matrix siempre intenta rellenar una cuadrícula 13×13
- Si tienes 169 items exactos, se rellenará perfectamente

**❌ Items no se renderizan**
- Verifica que `cellComponent` existe
- Prueba con `cellComponent="matrix.hand-cell"`

**❌ Estilos no se aplican**
- El archivo `matrix.blade.php` incluye estilos inline
- Si usas Tailwind, agrega clases de forma manual

---

## Próximas mejoras

- [ ] Soporte para drag & drop
- [ ] Animaciones al hover
- [ ] Export a CSV/JSON
- [ ] Zoom interactivo

