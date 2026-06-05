    <style>
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding-bottom: 15px;
        }

        .admin-nav h2 {
            margin: 0;
            color: var(--accent-blue);
            font-family: var(--font-heading);
            font-size: 1.5rem;
        }

        .btn-nav-back {
            background: #fff;
            border: 1px solid #cbd5e1;
            color: var(--text-primary);
            padding: 5px 8px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .btn-nav-back:hover {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.15);
        }

        /* Tabs layout */
        .tabs-header {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .tab-btn {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid #cbd5e1;
            color: var(--text-secondary);
            padding: 12px 24px;
            border-radius: 12px;
            font-family: var(--font-heading);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            white-space: nowrap;
        }

        .tab-btn.active {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.2);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Table styles */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        th {
            background: rgba(0, 0, 0, 0.02);
            border-bottom: 2px solid rgba(0, 0, 0, 0.06);
            color: var(--text-primary);
            font-weight: 700;
            padding: 4px 8px;
            font-family: var(--font-heading);
        }

        td {
            padding: 4px 8px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
        }

        .btn-table-action {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.08);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .btn-delete:hover {
            background: var(--error-red);
            color: #fff;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);
        }

        .btn-edit {
            background: rgba(0, 86, 179, 0.06);
            color: var(--accent-blue);
            border: 1px solid rgba(0, 86, 179, 0.15);
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: var(--accent-blue);
            color: #fff;
            box-shadow: 0 4px 10px rgba(0, 86, 179, 0.2);
        }

        .btn-return {
            background: rgba(16, 185, 129, 0.08);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        .btn-return:hover {
            background: var(--success-green);
            color: #fff;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .badge-borrowing {
            background: rgba(245, 158, 11, 0.08);
            color: var(--warning-yellow);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-returned {
            background: rgba(16, 185, 129, 0.08);
            color: var(--success-green);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Console Terminal UI style */
        .console-box {
            background: #0f172a; /* Console tối bóng bẩy */
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9rem;
            color: #38bdf8;
            max-height: 380px;
            height: 380px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.5;
            margin-top: 15px;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .console-controls {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            align-items: center;
        }

        .btn-console {
            background: linear-gradient(135deg, var(--accent-blue), #0099ff);
            color: #fff;
            font-family: var(--font-heading);
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.15);
        }

        .btn-console:hover {
            filter: brightness(1.05);
            box-shadow: 0 6px 18px rgba(0, 86, 179, 0.25);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .indicator-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #94a3b8;
        }

        .indicator-dot.active {
            background: var(--success-green);
            animation: pulse 1.2s infinite alternate;
        }

        @keyframes pulse {
            from { opacity: 0.4; }
            to { opacity: 1; box-shadow: 0 0 8px var(--success-green); }
        }

        /* Forms in admin cards */
        .admin-card {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--panel-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }

        .flex-box {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .thietbi-hinh {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #fff;
        }

        @media(max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
