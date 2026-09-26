@props(['variant' => 'primary', 'type' => 'button'])
<button type="{{ $type }}" {{ $attributes->class(['ui-button', 'ui-button-'.$variant]) }}>{{ $slot }}</button>
