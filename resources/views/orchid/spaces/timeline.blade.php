@push('stylesheets')
<style>
    .tl-filter-bar {
        display: flex;
        align-items: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .tl-filter-bar .tl-filter-group {
        display: flex;
        flex-direction: column;
    }
    .tl-filter-bar .tl-filter-group label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #8898aa;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .tl-track {
        position: relative;
        padding-left: 2rem;
    }
    .tl-track::before {
        content: '';
        position: absolute;
        left: 19px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }
    .tl-entry {
        position: relative;
        padding-bottom: 1.5rem;
        margin-bottom: 0;
    }
    .tl-entry:last-child {
        padding-bottom: 0;
    }
    .tl-dot {
        position: absolute;
        left: -2rem;
        top: 2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #e9ecef;
        z-index: 1;
        transform: translateX(calc(19px - 2rem + 2px));
    }
    .tl-dot.dot-success { background: #2dce89; box-shadow: 0 0 0 2px #2dce89; }
    .tl-dot.dot-info { background: #11cdef; box-shadow: 0 0 0 2px #11cdef; }
    .tl-dot.dot-warning { background: #fb6340; box-shadow: 0 0 0 2px #fb6340; }
    .tl-dot.dot-primary { background: #5e72e4; box-shadow: 0 0 0 2px #5e72e4; }
    .tl-dot.dot-secondary { background: #8898aa; box-shadow: 0 0 0 2px #8898aa; }
    .tl-dot.dot-dark { background: #212529; box-shadow: 0 0 0 2px #212529; }
    .tl-card {
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 0.5rem;
        padding: 1rem 1.25rem;
        transition: box-shadow 0.15s ease;
    }
    .tl-card:hover {
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .tl-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    .tl-card-header-left {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        min-width: 0;
    }
    .tl-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .tl-avatar-placeholder {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #fff;
        font-size: 13px;
    }
    .tl-label {
        font-weight: 600;
        font-size: 0.875rem;
        color: #32325d;
    }
    .tl-week-badge {
        font-size: 0.68rem;
        font-weight: 600;
        padding: 0.15rem 0.5rem;
        border-radius: 10px;
        background: #f4f5f7;
        color: #525f7f;
        white-space: nowrap;
    }
    .tl-timestamp {
        font-size: 0.75rem;
        color: #adb5bd;
        white-space: nowrap;
    }
    .tl-description {
        font-size: 0.8125rem;
        color: #525f7f;
        margin: 0;
        line-height: 1.5;
    }
    .tl-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }
    .tl-meta-item {
        font-size: 0.75rem;
        color: #8898aa;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .tl-status-change {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        margin-top: 0.5rem;
        padding: 0.35rem 0.75rem;
        background: #f8f9fe;
        border-radius: 0.375rem;
        font-size: 0.75rem;
    }
    .tl-status-badge {
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 600;
        font-size: 0.7rem;
        color: #fff;
    }
    .tl-status-badge.st-bad { background: #f5365c; }
    .tl-status-badge.st-good { background: #2dce89; }
    .tl-status-badge.st-warning { background: #fb6340; }
    .tl-comment-block {
        margin-top: 0.5rem;
        padding: 0.5rem 0.75rem;
        background: #f8f9fa;
        border-left: 3px solid #e9ecef;
        border-radius: 0 0.25rem 0.25rem 0;
        font-size: 0.8rem;
        color: #525f7f;
        font-style: italic;
    }
    .tl-criteria-list {
        margin-top: 0.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    .tl-criteria-tag {
        font-size: 0.7rem;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
        background: #ede9fe;
        color: #6b21a8;
        font-weight: 500;
    }
    .tl-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.625rem;
    }
    .tl-action-link {
        font-size: 0.75rem;
        font-weight: 500;
        color: #5e72e4;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: color 0.15s;
    }
    .tl-action-link:hover {
        color: #324cdd;
    }
    .tl-action-link.tl-action-maint {
        color: #fb6340;
    }
    .tl-action-link.tl-action-maint:hover {
        color: #d63a17;
    }
    .tl-empty {
        text-align: center;
        padding: 3rem 1rem;
    }
    .tl-empty-icon {
        font-size: 2.5rem;
        color: #dee2e6;
        margin-bottom: 0.75rem;
    }
    .tl-empty p {
        color: #8898aa;
        margin: 0;
        font-size: 0.875rem;
    }
    .tl-empty small {
        color: #adb5bd;
        font-size: 0.8rem;
    }
</style>
@endpush

{{-- Barra de Filtros --}}
<div class="bg-white rounded shadow-sm p-4 mb-4">
    <div class="row align-items-end g-3">
        <div class="col-12 col-md-auto" style="min-width: 240px;">
            <label class="form-label" style="font-weight: 500; font-size: 0.75rem; color: #8898aa; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.5rem;">Tipo de Actividad</label>
            <select id="filter_activity_type" class="form-select">
                <option value="">Todos los tipos</option>
                <option value="audit_created" @selected($activityFilters['activity_type'] === 'audit_created')>Auditoría Creada</option>
                <option value="audit_updated" @selected($activityFilters['activity_type'] === 'audit_updated')>Auditoría Actualizada</option>
                <option value="marked_third_party" @selected($activityFilters['activity_type'] === 'marked_third_party')>Marcado Tercero</option>
                <option value="resolution_uploaded" @selected($activityFilters['activity_type'] === 'resolution_uploaded')>Revisión Cargada</option>
                <option value="maintenance_requested" @selected($activityFilters['activity_type'] === 'maintenance_requested')>Mantenimiento Solicitado</option>
                <option value="maintenance_closed" @selected($activityFilters['activity_type'] === 'maintenance_closed')>Mantenimiento Cerrado</option>
                <option value="status_changed" @selected($activityFilters['activity_type'] === 'status_changed')>Estado Cambiado</option>
                <option value="comment_added" @selected($activityFilters['activity_type'] === 'comment_added')>Comentario Agregado</option>
            </select>
        </div>
        <div class="col-12 col-md-auto" style="min-width: 180px;">
            <label class="form-label" style="font-weight: 500; font-size: 0.75rem; color: #8898aa; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.5rem;">Desde</label>
            <input type="date" id="filter_date_from" class="form-control" value="{{ $activityFilters['date_from'] }}">
        </div>
        <div class="col-12 col-md-auto" style="min-width: 180px;">
            <label class="form-label" style="font-weight: 500; font-size: 0.75rem; color: #8898aa; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.5rem;">Hasta</label>
            <input type="date" id="filter_date_to" class="form-control" value="{{ $activityFilters['date_to'] }}">
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button type="button" class="btn btn-primary px-4" onclick="applyTimelineFilters()">
                Filtrar
            </button>
            @if($activityFilters['activity_type'] || $activityFilters['date_from'] || $activityFilters['date_to'])
                <button type="button" class="btn btn-outline-secondary px-4 bg-white" onclick="clearTimelineFilters()">
                    Limpiar
                </button>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    function applyTimelineFilters() {
        const type = document.getElementById('filter_activity_type').value;
        const from = document.getElementById('filter_date_from').value;
        const to = document.getElementById('filter_date_to').value;
        
        const url = new URL(window.location.href);
        if (type) url.searchParams.set('activity_type', type); else url.searchParams.delete('activity_type');
        if (from) url.searchParams.set('date_from', from); else url.searchParams.delete('date_from');
        if (to) url.searchParams.set('date_to', to); else url.searchParams.delete('date_to');
        
        // Mantener la pestaña activa
        url.hash = 'timeline';
        
        if (typeof window.Turbo !== 'undefined') {
            window.Turbo.visit(url.toString());
        } else {
            window.location.href = url.toString();
        }
    }

    function clearTimelineFilters() {
        const url = new URL(window.location.href);
        url.searchParams.delete('activity_type');
        url.searchParams.delete('date_from');
        url.searchParams.delete('date_to');
        url.hash = 'timeline';
        
        if (typeof window.Turbo !== 'undefined') {
            window.Turbo.visit(url.toString());
        } else {
            window.location.href = url.toString();
        }
    }
</script>
@endpush

{{-- Timeline --}}
<div class="bg-white rounded shadow-sm p-4">
    @forelse($activityLogs as $log)
        @php
            $dotClass = match($log->activity_type) {
                'audit_created' => 'dot-success',
                'audit_updated' => 'dot-info',
                'marked_third_party' => 'dot-warning',
                'resolution_uploaded' => 'dot-primary',
                'status_changed' => 'dot-secondary',
                'comment_added' => 'dot-dark',
                'maintenance_requested' => 'dot-warning',
                'maintenance_closed' => 'dot-success',
                default => 'dot-secondary',
            };
            $iconClass = match($log->activity_type) {
                'audit_created' => 'bg-success',
                'audit_updated' => 'bg-info',
                'marked_third_party' => 'bg-warning',
                'resolution_uploaded' => 'bg-primary',
                'status_changed' => 'bg-secondary',
                'comment_added' => 'bg-dark',
                'maintenance_requested' => 'bg-warning',
                'maintenance_closed' => 'bg-success',
                default => 'bg-secondary',
            };
            $statusLabel = fn($s) => match($s) {
                'good' => 'Bueno',
                'bad' => 'Malo',
                'warning' => 'Regular',
                default => ucfirst($s),
            };
            $statusBadge = fn($s) => match($s) {
                'bad' => 'st-bad',
                'good' => 'st-good',
                'warning' => 'st-warning',
                default => 'st-good',
            };
        @endphp
    @if($loop->first)
        <div class="tl-track">
    @endif
            <div class="tl-entry">
                <div class="tl-dot {{ $dotClass }}"></div>
                <div class="tl-card">
                    <div class="tl-card-header">
                        <div class="tl-card-header-left">
                            @if($log->user)
                                <img src="{{ $log->user->presenter()->image() }}" class="tl-avatar" alt="{{ $log->user->name }}">
                            @else
                                <div class="tl-avatar-placeholder {{ $iconClass }}">
                                    <i class="icon-user"></i>
                                </div>
                            @endif
                            <span class="tl-label">{{ $log->activity_label }}</span>
                            @if($log->week && $log->year)
                                <span class="tl-week-badge">S{{ $log->week }} / {{ $log->year }}</span>
                            @endif
                        </div>
                        <span class="tl-timestamp">{{ $log->created_at->format('d M Y, H:i') }}</span>
                    </div>

                    @php
                        $inlineStatus = $log->metadata['general_status'] ?? ($log->metadata['new_status'] ?? null);
                        $descCleaned = $inlineStatus
                            ? preg_replace('/\b(good|bad|warning)\b/i', '', $log->description)
                            : $log->description;
                    @endphp
                    <p class="tl-description">
                        {{ trim($descCleaned) }}
                        @if($inlineStatus)
                            <span class="tl-status-badge {{ $statusBadge($inlineStatus) }}">{{ $statusLabel($inlineStatus) }}</span>
                        @endif
                    </p>

                    <div class="tl-meta">
                        <span class="tl-meta-item">
                            <i class="icon-user"></i>
                            {{ $log->user->name ?? ($log->metadata['user_name'] ?? 'Sistema') }}
                        </span>
                        @if(isset($log->metadata['photo_path']) && $log->metadata['photo_path'])
                            <button type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#timelinePhotoModal"
                                    data-photo-url="{{ asset('storage/' . $log->metadata['photo_path']) }}"
                                    class="btn btn-link p-0 tl-meta-item btn-view-timeline-photo" style="text-decoration:none; font-size: 0.75rem; color: #2dce89;">
                                <i class="icon-camera"></i> Ver Foto
                            </button>
                        @endif
                    </div>

                    {{-- Status change --}}
                    @if($log->metadata && isset($log->metadata['old_status']) && isset($log->metadata['new_status']))
                        <div class="tl-status-change">
                            <span class="text-muted">Estado:</span>
                            <span class="tl-status-badge {{ $statusBadge($log->metadata['old_status']) }}">{{ $statusLabel($log->metadata['old_status']) }}</span>
                            <i class="icon-arrow-right" style="font-size: 10px; color: #adb5bd;"></i>
                            <span class="tl-status-badge {{ $statusBadge($log->metadata['new_status']) }}">{{ $statusLabel($log->metadata['new_status']) }}</span>
                        </div>
                    @endif

                    {{-- Comment --}}
                    @if($log->metadata && isset($log->metadata['comment']))
                        <div class="tl-comment-block">"{{ $log->metadata['comment'] }}"</div>
                    @endif

                    {{-- Changed criteria --}}
                    @if($log->metadata && isset($log->metadata['changed_criteria']))
                        <div class="tl-criteria-list">
                            <small class="text-muted me-1" style="font-size: 0.7rem;">Criterios:</small>
                            @foreach($log->metadata['changed_criteria'] as $criteria)
                                <span class="tl-criteria-tag">{{ $criteria }}</span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Contextual links --}}
                    @if($log->audit_id || isset($log->metadata['maintenance_id']))
                        <div class="tl-actions">
                            @if($log->audit_id)
                                <a href="{{ route('platform.audit.detail', $log->audit_id) }}" class="tl-action-link">
                                    <i class="icon-eye" style="font-size: 11px;"></i> Ver Auditoría
                                </a>
                            @endif
                            @if(isset($log->metadata['maintenance_id']))
                                <a href="{{ route('platform.maintenances.detail', $log->metadata['maintenance_id']) }}" class="tl-action-link tl-action-maint">
                                    <i class="icon-wrench" style="font-size: 11px;"></i> Ver Mantenimiento
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
    @if($loop->last)
        </div>
    @endif
    @empty
        <div class="tl-empty">
            <div class="tl-empty-icon"><i class="icon-clock"></i></div>
            <p>No hay actividad registrada para este espacio.</p>
            @if($activityFilters['activity_type'] || $activityFilters['date_from'] || $activityFilters['date_to'])
                <small>Intenta ajustar los filtros para ver más resultados.</small>
            @endif
        </div>
    @endforelse

    @if($activityLogs->hasPages())
        <div class="mt-4">
            {{ $activityLogs->withQueryString()->links() }}
        </div>
    @endif
</div>

{{-- Modal para fotos --}}
<div class="modal fade" id="timelinePhotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center px-3 pb-4">
                <img id="timelinePhotoImage" src="" class="img-fluid rounded" alt="Foto">
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-view-timeline-photo').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('timelinePhotoImage').src = this.getAttribute('data-photo-url');
        });
    });
});
</script>
@endpush
