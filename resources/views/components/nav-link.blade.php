@props(['route', 'label'])
@php($active = request()->routeIs($route))
<a href="{{ route($route) }}" class="{{ $active ? 'bg-care-mint text-care-green' : 'text-stone-700 hover:bg-stone-100' }} flex items-center rounded-md px-3 py-2 text-sm font-semibold">
    {{ $label }}
</a>
