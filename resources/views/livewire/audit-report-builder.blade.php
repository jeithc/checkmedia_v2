<div class="audit-report-builder">
    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Current Report Indicator --}}
    @if($currentReportName)
        <div class="alert alert-info d-flex align-items-center justify-content-between py-2 mb-3">
            <span>
                <i class="bi bi-file-earmark-text me-2"></i>
                Reporte cargado: <strong>{{ $currentReportName }}</strong>
            </span>
            <button wire:click="clearCurrentReport" class="btn btn-sm btn-outline-info">
                <i class="bi bi-x"></i> Limpiar
            </button>
        </div>
    @endif

    {{-- Tabs Navigation --}}
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button
                wire:click="$set('activeTab', 'builder')"
                class="nav-link {{ $activeTab === 'builder' ? 'active' : '' }}"
            >
                <i class="bi bi-sliders me-1"></i> Constructor
            </button>
        </li>
        <li class="nav-item">
            <button
                wire:click="$set('activeTab', 'shared')"
                class="nav-link {{ $activeTab === 'shared' ? 'active' : '' }}"
            >
                <i class="bi bi-archive me-1"></i> Reportes del Sistema
                @if(count($sharedReports) > 0)
                    <span class="badge bg-secondary ms-1">{{ count($sharedReports) }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button
                wire:click="$set('activeTab', 'personal')"
                class="nav-link {{ $activeTab === 'personal' ? 'active' : '' }}"
            >
                <i class="bi bi-person me-1"></i> Mis Reportes
                @if(count($myReports) > 0)
                    <span class="badge bg-primary ms-1">{{ count($myReports) }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button
                wire:click="$set('activeTab', 'advanced')"
                class="nav-link {{ $activeTab === 'advanced' ? 'active' : '' }}"
            >
                <i class="bi bi-lightning me-1"></i> Avanzados
                <span class="badge bg-warning text-dark ms-1">Pronto</span>
            </button>
        </li>
    </ul>

    {{-- Tab Content --}}
    @if($activeTab === 'builder')
        {{-- Filters Section --}}
        <div class="card mb-4">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h6>
            </div>
            <div class="card-body py-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Desde</label>
                        <input type="date" wire:model.live="filterDateFrom" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Hasta</label>
                        <input type="date" wire:model.live="filterDateTo" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Ciudad</label>
                        <input type="text" wire:model.live.debounce.300ms="filterCity" class="form-control form-control-sm" placeholder="Filtrar por ciudad...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tipo de Auditoría</label>
                        <select wire:model.live="filterAuditType" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option value="general">General</option>
                            <option value="structural">Estructural</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Builder Tab --}}
        <div class="row">
            {{-- Left Column: Column Selection --}}
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>Columnas Disponibles
                        </h6>
                        <small class="opacity-75">{{ count($selectedColumns) }} seleccionada(s)</small>
                    </div>
                    <div class="card-body" style="max-height: 450px; overflow-y: auto;">
                        {{-- Static Columns --}}
                        <h6 class="text-muted small fw-bold mb-2 text-uppercase">
                            <i class="bi bi-file-text me-1"></i> Datos Generales
                        </h6>
                        @foreach($availableStaticColumns as $key => $label)
                            <div class="form-check mb-1">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    wire:model.live="selectedColumns"
                                    value="{{ $key }}"
                                    id="col_{{ $key }}"
                                >
                                <label class="form-check-label small" for="col_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach

                        <hr>

                        {{-- Dynamic Criteria --}}
                        <h6 class="text-muted small fw-bold mb-2 text-uppercase">
                            <i class="bi bi-check2-square me-1"></i> Preguntas de Auditoria
                        </h6>
                        @forelse($availableCriteria as $criterion)
                            <div class="form-check mb-1">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    wire:model.live="selectedColumns"
                                    value="criterion_{{ $criterion->id }}"
                                    id="col_criterion_{{ $criterion->id }}"
                                >
                                <label class="form-check-label small" for="col_criterion_{{ $criterion->id }}">{{ $criterion->name }}</label>
                            </div>
                        @empty
                            <p class="text-muted small fst-italic">No hay criterios activos.</p>
                        @endforelse
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <div class="btn-group">
                                <button
                                    wire:click="generatePreview"
                                    wire:loading.attr="disabled"
                                    class="btn btn-outline-primary"
                                >
                                    <span wire:loading.remove wire:target="generatePreview">
                                        <i class="bi bi-eye me-1"></i> Vista Previa
                                    </span>
                                    <span wire:loading wire:target="generatePreview">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Cargando...
                                    </span>
                                </button>
                                <button
                                    wire:click="downloadExcel"
                                    wire:loading.attr="disabled"
                                    class="btn btn-success"
                                >
                                    <span wire:loading.remove wire:target="downloadExcel">
                                        <i class="bi bi-download me-1"></i> Excel
                                    </span>
                                    <span wire:loading wire:target="downloadExcel">
                                        <span class="spinner-border spinner-border-sm"></span>
                                    </span>
                                </button>
                            </div>
                            <button wire:click="openSaveModal" class="btn btn-dark">
                                <i class="bi bi-save me-1"></i> Guardar Configuracion
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Preview --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-table me-2"></i>Vista Previa
                        </h6>
                        <small class="opacity-75">Primeros 10 registros</small>
                    </div>
                    <div class="card-body p-0">
                        @if($showPreview && $previewData && count($selectedColumns) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            @foreach($selectedColumns as $column)
                                                <th class="small text-uppercase text-muted">
                                                    {{ $this->getColumnHeading($column) }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($previewData as $audit)
                                            <tr>
                                                @foreach($selectedColumns as $column)
                                                    <td class="small">{{ $this->getCellValue($audit, $column) }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ count($selectedColumns) }}" class="text-center py-4 text-muted">
                                                    No hay auditorias disponibles.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($previewData->count() > 0)
                                <div class="alert alert-info m-3 mb-0 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    El Excel contendra todos los registros, no solo esta vista previa.
                                </div>
                            @endif
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-table display-4 opacity-25"></i>
                                <h5 class="mt-3">Aun no hay vista previa</h5>
                                <p class="small">Seleccione columnas y haga clic en "Vista Previa".</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @elseif($activeTab === 'shared')
        {{-- Shared Reports Tab --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-archive me-2"></i>Reportes del Sistema</h6>
                <small class="text-muted">Disponibles para todos los usuarios</small>
            </div>
            <div class="list-group list-group-flush">
                @forelse($sharedReports as $report)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">{{ $report['name'] }}</h6>
                            @if($report['description'])
                                <small class="text-muted">{{ $report['description'] }}</small>
                            @endif
                            <br>
                            <small class="text-muted">
                                Por {{ $report['user']['name'] ?? 'Usuario' }} -
                                {{ count($report['selected_columns']) }} columnas
                            </small>
                        </div>
                        <div class="btn-group">
                            <button wire:click="loadReport({{ $report['id'] }})" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-folder2-open"></i> Cargar
                            </button>
                            @if($report['user_id'] === auth()->id())
                                <button
                                    wire:click="deleteReport({{ $report['id'] }})"
                                    wire:confirm="Eliminar este reporte?"
                                    class="btn btn-sm btn-outline-danger"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-center py-5 text-muted">
                        <i class="bi bi-archive display-4 opacity-25"></i>
                        <p class="mt-3 mb-0">No hay reportes del sistema disponibles.</p>
                        @if($this->canCreateSharedReports())
                            <small>Crea uno desde el Constructor marcando "Compartir con todos".</small>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

    @elseif($activeTab === 'personal')
        {{-- Personal Reports Tab --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i>Mis Reportes</h6>
                <small class="text-muted">Solo visibles para ti</small>
            </div>
            <div class="list-group list-group-flush">
                @forelse($myReports as $report)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">{{ $report['name'] }}</h6>
                            @if($report['description'])
                                <small class="text-muted">{{ $report['description'] }}</small>
                            @endif
                            <br>
                            <small class="text-muted">{{ count($report['selected_columns']) }} columnas</small>
                        </div>
                        <div class="btn-group">
                            <button wire:click="loadReport({{ $report['id'] }})" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-folder2-open"></i> Cargar
                            </button>
                            <button
                                wire:click="deleteReport({{ $report['id'] }})"
                                wire:confirm="Eliminar este reporte?"
                                class="btn btn-sm btn-outline-danger"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-center py-5 text-muted">
                        <i class="bi bi-folder display-4 opacity-25"></i>
                        <p class="mt-3 mb-0">No tienes reportes guardados.</p>
                        <small>Ve al Constructor y guarda tu primera configuracion.</small>
                    </div>
                @endforelse
            </div>
        </div>

    @elseif($activeTab === 'advanced')
        {{-- Advanced Reports Tab --}}
        <div class="card">
            <div class="card-header bg-warning">
                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Reportes Avanzados</h6>
                <small>Proximamente disponible</small>
            </div>
            <div class="card-body text-center py-5">
                <i class="bi bi-lightning display-1 text-warning opacity-50"></i>
                <h4 class="mt-3">Reportes con Agregaciones</h4>
                <p class="text-muted">
                    Pronto podras crear reportes avanzados con conteos, agrupaciones y filtros personalizados.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-light text-dark">Conteos por campo</span>
                    <span class="badge bg-light text-dark">Agrupaciones</span>
                    <span class="badge bg-light text-dark">Filtros avanzados</span>
                    <span class="badge bg-light text-dark">Graficos</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Save Modal --}}
    @if($showSaveModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-save me-2"></i>Guardar Configuracion
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeSaveModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Reporte *</label>
                            <input
                                type="text"
                                wire:model="newReportName"
                                class="form-control @error('newReportName') is-invalid @enderror"
                                placeholder="Ej: Reporte Semanal Bogota"
                            >
                            @error('newReportName')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripcion (opcional)</label>
                            <textarea
                                wire:model="newReportDescription"
                                class="form-control"
                                rows="2"
                                placeholder="Breve descripcion..."
                            ></textarea>
                        </div>
                        @if($this->canCreateSharedReports())
                            <div class="form-check mb-3">
                                <input
                                    type="checkbox"
                                    wire:model="newReportIsShared"
                                    class="form-check-input"
                                    id="shareReport"
                                >
                                <label class="form-check-label" for="shareReport">
                                    Compartir con todos los usuarios
                                </label>
                                <small class="d-block text-muted">Aparecera en "Reportes del Sistema"</small>
                            </div>
                        @endif
                        <div class="alert alert-secondary small mb-0">
                            <strong>Columnas seleccionadas:</strong> {{ count($selectedColumns) }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeSaveModal">Cancelar</button>
                        <button type="button" class="btn btn-primary" wire:click="saveReport" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveReport">Guardar Reporte</span>
                            <span wire:loading wire:target="saveReport">
                                <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
