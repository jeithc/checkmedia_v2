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

        .pf-label {
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

        /* Select-pill: mismo lenguaje que los chips, label integrado */
        .pf-select {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: 0 .85rem;
            height: 30px;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #fff;
            font-size: .8125rem;
            color: #495057;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .pf-select:focus-within {
            border-color: #adb5bd;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }

        .pf-select > span {
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #8a919a;
            white-space: nowrap;
        }

        /* Inputs (date, text) inside a pill: drop the native chrome so they match the select */
        .pf-select input {
            border: 0;
            outline: 0;
            background: transparent;
            font-size: .8125rem;
            font-weight: 500;
            color: #212529;
            padding: 0;
            width: auto;
            font-family: inherit;
        }
        .pf-select input[type="date"] {
            width: 7.6rem;
        }
        .pf-select input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: .55;
            cursor: pointer;
            margin-left: .2rem;
        }

        .pf-select select {
            border: 0;
            background: transparent;
            font-size: .8125rem;
            font-weight: 500;
            color: #212529;
            padding-right: 1.1rem;
            width: auto;
            max-width: 150px;
            text-overflow: ellipsis;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%238a919a' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .1rem center;
        }

        .pf-select select:focus {
            outline: none;
        }

        /* Search pill */
        .pf-search {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: 0 .85rem;
            height: 30px;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #fff;
            color: #8a919a;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .pf-search:focus-within {
            border-color: #adb5bd;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }

        .pf-search {
            flex: 1 1 auto;
            min-width: 170px;
            max-width: 320px;
        }

        .pf-search input {
            border: 0;
            background: transparent;
            font-size: .8125rem;
            color: #212529;
            width: 100%;
            min-width: 0;
        }

        .pf-search input:focus {
            outline: none;
        }

        /* Dot de estado en chips de estado */
        .pf-dot--good { --pf-dot-color: #059669; }
        .pf-dot--bad { --pf-dot-color: #dc2626; }

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
            white-space: nowrap; /* a pill must never wrap onto two lines */
        }
        /* A pill used as a link (row action) keeps its colors on hover instead of turning into a blue link */
        a.mnt-badge:hover { filter: brightness(.95); text-decoration: none; }
        a.mnt-badge--bad, a.mnt-badge--bad:hover { color: #991b1b; }

        /* Row "view" icon: force a visible color; Orchid's link color can render it white on white */
        .mnt-row-view, .mnt-row-view:hover { color: #495057 !important; }

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
    </style>
@endpush
