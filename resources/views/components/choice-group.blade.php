@props(['name', 'options', 'default' => null])

<div class="choices">
    @foreach ($options as $value => $label)
        <label class="choice">
            <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $default) === $value) required>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
@error($name)
    <p class="field-error">{{ $message }}</p>
@enderror
