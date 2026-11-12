@extends('app')

@section('content')
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Encabezado -->
    <x-form-header
      :title="$copyFrom ? __('bots.copy') : __('bots.create_new')"
      :description="$copyFrom ? __('bots.description_copy') . ' ' . $copyFrom->nombre : __('bots.description_create')"
      :cancelUrl="route('bots.index')"
    />

    <!-- Formulario -->
    <form method="POST" action="{{ route('bots.store') }}">
      @csrf

      <!-- Componente de formulario reutilizable -->
      <x-bots.bot-form :copyFrom="$copyFrom" />

      <!-- Componente de niveles -->
      <x-bots.bot-levels-form :bot="$copyFrom" />

      <!-- Botones de Acción -->
      <x-form-actions
        :submitText="$copyFrom ? __('bots.copy_button') : __('bots.create_button')"
        submitColor="#3b82f6"
        :cancelUrl="route('bots.index')"
      />
    </form>
  </main>
@endsection
