@push('head')
    @livewireStyles
    @livewireScripts
@endpush

<div id="livewire-dashboard-container">
    @livewire('admin-dashboard')
</div>