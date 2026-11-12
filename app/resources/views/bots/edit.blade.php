@extends('app')

@section('content')
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Encabezado -->
    <x-form-header
      :title="__('bots.edit') . ' ' . $bot->nombre"
      :description="__('bots.description_edit')"
      :cancelUrl="route('bots.show', $bot)"
    />

    <!-- Formulario -->
    <form method="POST" action="{{ route('bots.update', $bot) }}">
      @csrf
      @method('PATCH')

      <!-- Componente de formulario reutilizable -->
      <x-bots.bot-form :bot="$bot" />

      <!-- Componente de niveles -->
      <x-bots.bot-levels-form :bot="$bot" />

      <!-- Botones de Acción -->
      <x-form-actions
        :submitText="__('bots.save_changes')"
        submitColor="#10b981"
        :cancelUrl="route('bots.show', $bot)"
      />
    </form>
  </main>
@endsection
