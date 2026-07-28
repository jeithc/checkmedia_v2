{{-- Sistema visual compartido: chips de producto + tabla compacta (maintenances, spaces). --}}
@push('stylesheets')
    <style>
        /* ===== Chips de producto ===== */
        .product-filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }

        .product-filter-bar .pf-label {
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6c757d;
            margin-right: .25rem;
        }

        .pf-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .35rem .85rem;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #fff;
            color: #495057;
            font-size: .8125rem;
            font-weight: 500;
            line-height: 1;
            text-decoration: none;
            transition: border-color .15s ease, box-shadow .15s ease, color .15s ease;
        }

        .pf-chip:hover {
            border-color: #adb5bd;
            color: #212529;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }

        .pf-chip.active {
            background: #212529;
            border-color: #212529;
            color: #fff;
        }

        /* Dot: sólido = estático/físico, hueco = digital */
        .pf-dot,
        .mnt-dot {
            width: .5rem;
            height: .5rem;
            border-radius: 50%;
            flex-shrink: 0;
            background: var(--pf-dot-color, var(--mnt-dot-color));
        }

        .pf-dot.hollow,
        .mnt-dot.hollow {
            background: transparent !important;
            box-shadow: inset 0 0 0 2px var(--pf-dot-color, var(--mnt-dot-color));
        }

        .pf-chip.active .pf-dot:not(.hollow) {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, .6);
        }

        /* ===== Tabla compacta (acotada a tablas con .mnt-product) ===== */
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

        .mnt-product {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
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

        /* Estado de última auditoría (spaces) */
        .mnt-badge--good { background: #d1fae5; color: #065f46; }
        .mnt-badge--bad { background: #fee2e2; color: #991b1b; }
        .mnt-badge--warning { background: #fef3c7; color: #92400e; }
    </style>
@endpush
