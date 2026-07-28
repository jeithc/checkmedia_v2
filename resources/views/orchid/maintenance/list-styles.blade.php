{{-- Estilos de la tabla de mantenimientos. Acotados vía :has(.mnt-product) a esta tabla. --}}
@push('stylesheets')
    <style>
        /* Densidad y alineación */
        .table:has(.mnt-product) td {
            padding-top: .6rem;
            padding-bottom: .6rem;
            vertical-align: middle;
            font-size: .8438rem;
            white-space: nowrap;
        }

        .table:has(.mnt-product) thead th {
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #8a919a;
            white-space: nowrap;
        }

        .table:has(.mnt-product) tbody tr:hover {
            background: #f8f9fb;
        }

        /* Datos numéricos alineados en columna */
        .mnt-num {
            font-variant-numeric: tabular-nums;
        }

        .mnt-muted {
            color: #8a919a;
        }

        .mnt-code {
            font-weight: 600;
            color: #1f2937;
            font-variant-numeric: tabular-nums;
        }

        /* Producto: mismo vocabulario del filtro (dot de familia, hueco = digital) */
        .mnt-product {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
        }

        .mnt-dot {
            width: .5rem;
            height: .5rem;
            border-radius: 50%;
            flex-shrink: 0;
            background: var(--mnt-dot-color);
        }

        .mnt-dot.hollow {
            background: transparent;
            box-shadow: inset 0 0 0 2px var(--mnt-dot-color);
        }

        /* Badges tintados: texto oscuro sobre tinte suave */
        .mnt-badge {
            display: inline-flex;
            align-items: center;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
            line-height: 1;
        }

        .mnt-badge--alta { background: #fee2e2; color: #991b1b; }
        .mnt-badge--media { background: #fef3c7; color: #92400e; }
        .mnt-badge--baja { background: #e0f2fe; color: #075985; }
        .mnt-badge--none { background: #f1f3f5; color: #6b7280; }

        .mnt-badge--reported { background: #f1f3f5; color: #4b5563; }
        .mnt-badge--pending_advisual { background: #fef3c7; color: #92400e; }
        .mnt-badge--in_progress { background: #dbeafe; color: #1e40af; }
        .mnt-badge--closed { background: #d1fae5; color: #065f46; }
    </style>
@endpush
