@props(['action', 'message' => 'لا يمكن التراجع عن هذه العملية.', 'label' => 'حذف', 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'method' => 'DELETE', 'title' => 'تأكيد الحذف'])
<form method="POST" action="{{ $action }}" class="d-inline" data-confirm="{{ $message }}" data-confirm-title="{{ $title }}" data-confirm-button="نعم، تأكيد">
    @csrf @method($method)
    {{ $slot }}
    <button type="submit" class="{{ $class }}" data-no-loading title="{{ $label }}"><i class="bi bi-{{ $icon }}"></i> <span class="d-none d-xl-inline">{{ $label }}</span></button>
</form>
