@extends('layouts.app')
@section('title', 'رسائل التواصل')
@section('content')
<x-page-header title="رسائل التواصل من الموقع" :crumbs="['رسائل التواصل']" />
@forelse ($messages as $m)
    <div class="card mb-2"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div><strong>{{ $m->name }}</strong> @if (! $m->read_at)<span class="badge bg-warning text-dark">جديدة</span>@endif
                <small class="text-muted d-block" dir="ltr">{{ $m->email }} {{ $m->phone }}</small></div>
            <div class="d-flex gap-2 align-items-start"><small class="text-muted">{{ $m->created_at->format('Y/m/d H:i') }}</small>
                <x-delete-form :action="route('admin.contact.destroy', $m)" label="" message="سيتم حذف الرسالة." /></div>
        </div>
        <p class="mb-0 mt-2" style="white-space: pre-line">{{ $m->message }}</p>
    </div></div>
@empty
    <div class="card"><x-empty icon="envelope" message="لا توجد رسائل." /></div>
@endforelse
{{ $messages->links() }}
@endsection
