@props(['tone' => 'neutral'])
<span {{ $attributes->class(['ui-badge', 'ui-badge-'.$tone]) }}>{{ $slot }}</span>
