@extends('layout')

@section('title', 'Welcome')

@section('content')

<!-- Welcome -->
<div class="container d-flex justify-content-center align-items-center" style="height: 85vh">
    @auth
    <h1 class="fw-bold display-4">
        Welcome, <span class="text-primary">{{ auth()->user()->first_name }}</span>
    </h1>
@else
    <h1 class="fw-bold display-4">
        Welcome, <span class="text-primary">Guest</span>
    </h1>
    @endauth
</div>
@endsection
