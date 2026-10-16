@extends('layouts.public')
@section('body')
<div class="auth-wrap">
    <div class="card auth-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-center mb-4">@include('partials.brand')</div>
            @yield('form')
        </div>
    </div>
</div>
@endsection
