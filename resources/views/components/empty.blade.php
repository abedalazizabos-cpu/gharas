@props(['icon' => 'inbox', 'message' => 'لا توجد بيانات لعرضها.'])
<div class="empty-state">
    <i class="bi bi-{{ $icon }}"></i>
    <div>{{ $message }}</div>
    {{ $slot }}
</div>
