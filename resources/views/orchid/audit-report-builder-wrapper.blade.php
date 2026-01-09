@push('head')
    @livewireStyles
@endpush

@push('scripts')
    @livewireScripts
@endpush

<div class="audit-report-builder-wrapper">
    @livewire('audit-report-builder')
</div>
