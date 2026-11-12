@props([
    'heroStack' => 0,
    'toCall' => 0
])

<div style="display: flex; flex-direction: column; gap: 0.75rem;">
  <button @click="playerAction('fold')"
    style="background-color: #7f1d1d; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: 1px solid #991b1b; transition: all 0.2s;"
    onmouseover="this.style.backgroundColor='#991b1b'"
    onmouseout="this.style.backgroundColor='#7f1d1d'"
    onfocus="this.style.backgroundColor='#991b1b'">
    1. Retirarse (Fold)
  </button>

  <button @click="playerAction('call')"
    style="background-color: #1e40af; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: 1px solid #1e3a8a; transition: all 0.2s;"
    onmouseover="this.style.backgroundColor='#1e3a8a'"
    onmouseout="this.style.backgroundColor='#1e40af'">
    2. Igualar {{ $toCall }} bb
  </button>

  <button @click="playerAction('raise2x')"
    style="background-color: #b45309; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: 1px solid #92400e; transition: all 0.2s;"
    onmouseover="this.style.backgroundColor='#92400e'"
    onmouseout="this.style.backgroundColor='#b45309'">
    3. Subir 2.2x bb
  </button>

  <button @click="playerAction('raise3x')"
    style="background-color: #92400e; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: 1px solid #78350f; transition: all 0.2s;"
    onmouseover="this.style.backgroundColor='#78350f'"
    onmouseout="this.style.backgroundColor='#92400e'">
    4. Subir 3.0x bb
  </button>

  <button @click="playerAction('all_in')"
    style="background-color: #9f1239; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: 1px solid #be123c; transition: all 0.2s;"
    onmouseover="this.style.backgroundColor='#be123c'"
    onmouseout="this.style.backgroundColor='#9f1239'">
    5. All-in ({{ $heroStack }} bb)
  </button>
</div>
