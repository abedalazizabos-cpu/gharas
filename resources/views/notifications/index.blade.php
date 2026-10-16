@extends('layouts.app')
@section('title', 'الإشعارات')
@section('content')
<x-page-header title="الإشعارات" :crumbs="['الإشعارات']">
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-check2-all"></i> تعليم الكل كمقروء</button></form>
</x-page-header>
<div class="card">
    <ul class="list-group list-group-flush">
        @forelse ($notifications as $n)
            <li class="list-group-item d-flex gap-3 align-items-start {{ $n->read_at ? '' : 'bg-green-soft' }}">
                <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} fs-4 text-success"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $n->data['title'] ?? '' }}</div>
                    <div class="small">{{ $n->data['body'] ?? '' }}</div>
                    <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
                </div>
                @if (! empty($n->data['url']))
                    <form method="POST" action="{{ route('notifications.read', $n->id) }}" data-no-loading>@csrf<button class="btn btn-sm btn-outline-secondary">فتح</button></form>
                @endif
            </li>
        @empty
            <li class="list-group-item"><x-empty icon="bell-slash" message="لا توجد إشعارات." /></li>
        @endforelse
    </ul>
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
