@props([
    'heroStack' => 0,
    'toCall' => 0
])

<div style="display: flex; flex-direction: column; gap: 0.75rem;">
  <style>
    .action-button--fold,
    .action-button--call,
    .action-button--raise2x,
    .action-button--raise3x,
    .action-button--all-in {
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .action-button--fold {
      background-color: #7f1d1d;
      border: 1px solid #991b1b;
    }

    .action-button--fold:hover,
    .action-button--fold:focus-visible {
      background-color: #991b1b !important;
    }

    .action-button--call {
      background-color: #1e40af;
      border: 1px solid #1e3a8a;
    }

    .action-button--call:hover,
    .action-button--call:focus-visible {
      background-color: #1e3a8a;
    }

    .action-button--raise2x {
      background-color: #b45309;
      border: 1px solid #92400e;
    }

    .action-button--raise2x:hover,
    .action-button--raise2x:focus-visible {
      background-color: #92400e;
    }

    .action-button--raise3x {
      background-color: #92400e;
      border: 1px solid #78350f;
    }

    .action-button--raise3x:hover,
    .action-button--raise3x:focus-visible {
      background-color: #78350f;
    }

    .action-button--all-in {
      background-color: #9f1239;
      border: 1px solid #be123c;
    }

    .action-button--all-in:hover,
    .action-button--all-in:focus-visible {
      background-color: #be123c;
    }
  </style>

  <button @click="playerAction('fold')"
    class="action-button--fold"
    >
    1. Retirarse (Fold)
  </button>

  <button @click="playerAction('call')"
    class="action-button--call">
    2. Igualar {{ $toCall }} bb
  </button>

  <button @click="playerAction('raise2x')"
    class="action-button--raise2x">
    3. Subir 2.2x bb
  </button>

  <button @click="playerAction('raise3x')"
    class="action-button--raise3x">
    4. Subir 3.0x bb
  </button>

  <button @click="playerAction('all_in')"
    class="action-button--all-in">
    5. All-in ({{ $heroStack }} bb)
  </button>
</div>
