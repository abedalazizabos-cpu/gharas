@extends('layouts.app')
@section('title', 'الإعلانات')
@section('content')
<x-page-header title="الإعلانات" :crumbs="['الإعلانات']" />
@forelse ($announcements as $a)
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <h5 class="fw-bold mb-1">{{ $a->title }}</h5>
            <small class="text-muted">{{ ($a->publish_at ?? $a->created_at)->format('Y/m/d') }}</small>
        </div>
        <p class="mb-0" style="white-space: pre-line">{{ $a->body }}</p>
    </div></div>
@empty
    <div class="card"><x-empty icon="megaphone" message="لا توجد إعلانات حالياً." /></div>
@endforelse
{{ $announcements->links() }}
@endsection
