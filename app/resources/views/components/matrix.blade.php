{{-- Componente Matrix para renderizar matrices de 13x13 (poker hands) --}}

<div id="{{ $id }}" class="matrix-container {{ $class ?? '' }}">
  <div class="matrix-wrapper">
    @foreach($rows() as $rowIndex => $row)
      <div class="matrix-row" data-row="{{ $rowIndex }}">
        @foreach($row as $cellIndex => $item)
          @php
            $globalIndex = $getItemIndex($rowIndex, $cellIndex);
          @endphp

          @if($item)
            <x-dynamic-component
              :component="$cellComponent"
              :item="$item"
              :rowIndex="$rowIndex"
              :cellIndex="$cellIndex"
              :globalIndex="$globalIndex"
            />
          @else
            <div class="matrix-cell empty" data-index="{{ $globalIndex }}"></div>
          @endif
        @endforeach
      </div>
    @endforeach
  </div>
</div>

<style>
  .matrix-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 0.5rem;
  }

  .matrix-wrapper {
    display: inline-block;
    min-width: 100%;
  }

  .matrix-row {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--matrix-border, #2e3a48);
  }

  .matrix-row:last-child {
    border-bottom: none;
  }

  .matrix-cell {
    flex: 1;
    aspect-ratio: 1;
    min-width: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid var(--matrix-border, #2e3a48);
    padding: 0.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
  }

  .matrix-cell:last-child {
    border-right: none;
  }

  .matrix-cell:hover:not(.empty) {
    transform: scale(1.05);
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  }

  .matrix-cell.empty {
    background-color: rgba(15, 20, 25, 0.3);
    cursor: default;
  }

  .matrix-cell.empty:hover {
    transform: none;
    box-shadow: none;
  }

  @media (max-width: 768px) {
    .matrix-cell {
      min-width: 30px;
      font-size: 0.75rem;
      padding: 0.125rem;
    }
  }

  @media (max-width: 480px) {
    .matrix-cell {
      min-width: 24px;
      font-size: 0.65rem;
      padding: 0;
      aspect-ratio: 1;
    }
  }
</style>
