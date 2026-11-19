@extends('layouts.base')

@section('title', 'Payment')

@section('body')
    <main class="mx-auto max-w-lg p-8">
        <h1 class="text-xl font-semibold">Payment status</h1>
        <p class="mt-3">{{ ucfirst(strtolower($status)) }}</p>
        <p class="mt-2">You can return to Fish Happy.</p>
    </main>
@endsection
