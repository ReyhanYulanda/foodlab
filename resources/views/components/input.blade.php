@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge([
    'class' => 'rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:outline-none focus:ring-0',
]) !!}>
