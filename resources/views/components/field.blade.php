@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'col' => 'col-md-6', 'help' => null])
<div class="{{ $col }}">
    <label for="{{ $name }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['rows' => 3, 'class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }} @required($required)>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }} @required($required)>
    @endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
