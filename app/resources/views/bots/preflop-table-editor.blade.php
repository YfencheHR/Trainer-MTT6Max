@extends('app')

@section('content')
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    @include('bots._pool-preflop-tables', [
      'bot' => $bot,
      'tables' => $tables,
      'onlyPosition' => $onlyPosition,
      'onlyType' => $onlyType,
      'editorPage' => true,
    ])
  </main>
@endsection
