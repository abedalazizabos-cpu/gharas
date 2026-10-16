@extends('layouts.app')
@section('title', $message->subject)
@section('content')
<x-page-header :title="$message->subject" :crumbs="['الرسائل' => route('messages.index'), 'عرض رسالة']">
    @php($other = $message->sender_id === auth()->id() ? $message->recipient : $message->sender)
    <a href="{{ route('messages.create', ['to' => $other->id, 'student' => $message->student_id, 'subject' => 'رد: '.$message->subject]) }}" class="btn btn-primary"><i class="bi bi-reply"></i> رد</a>
</x-page-header>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2 fw-normal">
        <span><strong>من:</strong> {{ $message->sender->name }} &nbsp; <strong>إلى:</strong> {{ $message->recipient->name }} @if ($message->student) &nbsp; <strong>بخصوص:</strong> {{ $message->student->full_name }} @endif</span>
        <small class="text-muted">{{ $message->created_at->format('Y/m/d H:i') }}</small>
    </div>
    <div class="card-body" style="white-space: pre-line">{{ $message->body }}</div>
</div>
@endsection
