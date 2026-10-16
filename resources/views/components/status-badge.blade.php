@props(['status', 'label'])
@php($color = ['active' => 'success', 'suspended' => 'warning', 'withdrawn' => 'secondary', 'inactive' => 'secondary'][$status] ?? 'secondary')
<span class="badge bg-{{ $color }}-subtle text-{{ $color }}-emphasis border border-{{ $color }}-subtle">{{ $label }}</span>
