@props(['name', 'label', 'hint' => null])
@php($errorId = $name.'-error')
<div>
    <label class="field-label" for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $errorId }}" @enderror
        {{ $attributes->class(['input', 'input-invalid' => $errors->has($name)]) }}
    >
    @error($name)
        <p class="field-error" id="{{ $errorId }}">{{ $message }}</p>
    @enderror
    @if ($hint !== null)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
