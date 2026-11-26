<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Matrix extends Component
{
    /**
     * Componente para renderizar matrices de poker (13×13 manos)
     *
     * @param  int  $columns  - Número de columnas (típicamente 13)
     * @param  array  $items  - Array de items para renderizar
     * @param  string  $cellComponent  - Componente a usar por celda
     * @param  string  $id  - ID HTML del contenedor
     * @param  string  $class  - Clases CSS adicionales
     */
    public function __construct(
        public int $columns = 13,
        public array $items = [],
        public string $cellComponent = 'matrix.cell',
        public string $id = 'matrix',
        public string $class = ''
    ) {}

    public function render()
    {
        return view('components.matrix');
    }

    /**
     * Divide los items en filas según el número de columnas
     */
    public function rows(): array
    {
        return array_chunk($this->items, $this->columns);
    }

    /**
     * Obtiene índice global de una celda en una posición
     */
    public function getItemIndex(int $rowIndex, int $cellIndex): int
    {
        return $rowIndex * $this->columns + $cellIndex;
    }
}
