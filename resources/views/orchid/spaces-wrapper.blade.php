@push('head')
    @livewireStyles
@endpush

@push('scripts')
    @livewireScripts
@endpush

<div class="space-browser-wrapper">
    @livewire('space-browser')
</div>
