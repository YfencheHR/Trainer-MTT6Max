<?php extract((new \Illuminate\Support\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
@props(['item'])
<x-matrix.hand-cell :item="$item" >

{{ $slot ?? "" }}
</x-matrix.hand-cell>