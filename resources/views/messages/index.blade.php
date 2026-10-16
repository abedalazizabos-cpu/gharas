@extends('layouts.app')
@section('title', 'الرسائل')
@section('content')
<x-page-header title="الرسائل" :crumbs="['الرسائل']">
    <a href="{{ route('messages.create') }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i> رسالة جديدة</a>
</x-page-header>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ $box === 'inbox' ? 'active' : '' }}" href="{{ route('messages.index') }}"><i class="bi bi-inbox"></i> الوارد</a></li>
    <li class="nav-item"><a class="nav-link {{ $box === 'sent' ? 'active' : '' }}" href="{{ route('messages.index', ['box' => 'sent']) }}"><i class="bi bi-send"></i> المرسل</a></li>
</ul>
<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($messages as $m)
            <a href="{{ route('messages.show', $m) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-2 {{ $box === 'inbox' && ! $m->read_at ? 'fw-bold bg-green-soft' : '' }}">
                <span>
                    <span class="d-block">{{ $m->subject }}</span>
                    <small class="text-muted fw-normal">{{ $box === 'inbox' ? 'من: '.$m->sender->name : 'إلى: '.$m->recipient->name }} @if ($m->student)· بخصوص {{ $m->student->full_name }}@endif</small>
                </span>
                <small class="text-muted text-nowrap fw-normal">{{ $m->created_at->diffForHumans() }}</small>
            </a>
        @empty
            <div class="list-group-item"><x-empty icon="chat-dots" message="لا توجد رسائل." /></div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $messages->links() }}</div>
@endsection
