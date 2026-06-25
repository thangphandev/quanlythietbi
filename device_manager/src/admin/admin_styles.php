    <style>
        /* Bố cục Admin Sidebar mới */
        .admin-layout {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .admin-sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .sidebar-header h2 {
            font-family: var(--font-heading);
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--accent-blue);
            margin: 0;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--accent-blue) 30%, #0099ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-close-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px;
            line-height: 1;
        }

        .sidebar-menu {
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
            overflow-y: auto;
        }

        .sidebar-menu .tab-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-secondary);
            padding: 12px 18px;
            border-radius: 12px;
            font-family: var(--font-heading);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: normal;
            text-align: left;
            width: 100%;
        }

        .sidebar-menu .tab-btn:hover {
            background: rgba(0, 86, 179, 0.05);
            color: var(--accent-blue);
            transform: translateX(4px);
        }

        .sidebar-menu .tab-btn.active {
            background: linear-gradient(135deg, var(--accent-blue), #0077ee);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.15);
        }

        .sidebar-footer {
            padding: 20px 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn-sidebar-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #cbd5e1;
            color: var(--text-primary);
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-align: center;
        }

        .btn-sidebar-back:hover {
            background: rgba(0, 86, 179, 0.05);
            color: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        .btn-sidebar-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-align: center;
        }

        .btn-sidebar-logout:hover {
            background: var(--error-red);
            color: #fff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }

        .admin-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100vh;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-topbar {
            height: 65px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding: 5px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-toggle-sidebar {
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-toggle-sidebar:hover {
            background: rgba(0, 86, 179, 0.05);
            color: var(--accent-blue);
            border-color: rgba(0, 86, 179, 0.1);
        }

        .topbar-title {
            font-family: var(--font-heading);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-user-info {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .btn-topbar-nav {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        .btn-topbar-nav:hover {
            background: rgba(0, 86, 179, 0.05);
            color: var(--accent-blue);
        }

        .btn-topbar-logout {
            font-size: 0.85rem;
            font-weight: 600;
            color: #ef4444;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
            transition: all 0.2s;
        }

        .btn-topbar-logout:hover {
            background: var(--error-red);
            color: #fff;
        }

        .admin-content-container {
            padding: 10px;
            flex-grow: 1;
            overflow-y: auto;
            max-width: 100%;
        }

        .admin-content-card {
            background: var(--panel-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--panel-border);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-premium);
            padding: 24px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.3);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        /* Sidebar Collapsed State (Desktop) */
        .admin-layout.sidebar-collapsed .admin-sidebar {
            width: 0;
            overflow: hidden;
            border-right-color: transparent;
        }

        /* Sidebar Open State (Mobile & Tablet) */
        @media (max-width: 992px) {
            .admin-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                transform: translateX(-100%);
                box-shadow: 15px 0 30px rgba(0, 0, 0, 0.1);
            }
            
            .btn-close-sidebar {
                display: block;
            }
            
            .sidebar-overlay {
                display: block;
            }
            
            .admin-layout.sidebar-active .admin-sidebar {
                transform: translateX(0);
            }
            
            .admin-layout.sidebar-active .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }
            
            .admin-topbar {
                padding: 5px 15px;
            }
            
            .topbar-right .admin-user-info {
                display: none;
            }
        }

        /* Tabs layout */
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

        button.btn-table-action,
        a.btn-table-action,
        .btn-table-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 28px;
            padding: 0 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            text-decoration: none;
            box-sizing: border-box;
            border: 1px solid transparent;
            /* Reset button[type="submit"] overrides */
            width: auto;
            margin-top: 0;
            box-shadow: none;
            font-family: var(--font-body);
        }

        button.btn-table-action:hover,
        a.btn-table-action:hover,
        .btn-table-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        button.btn-table-action:active,
        a.btn-table-action:active,
        .btn-table-action:active {
            transform: translateY(0);
        }

        .btn-action-qr {
            background: rgba(13, 148, 136, 0.06);
            color: #0d9488;
            border-color: rgba(13, 148, 136, 0.15);
        }

        .btn-action-qr:hover {
            background: #0d9488;
            color: #fff;
            border-color: #0d9488;
        }

        .btn-action-history {
            background: rgba(124, 58, 237, 0.06);
            color: #7c3aed;
            border-color: rgba(124, 58, 237, 0.15);
        }

        .btn-action-history:hover {
            background: #7c3aed;
            color: #fff;
            border-color: #7c3aed;
        }

        button.btn-edit,
        a.btn-edit,
        .btn-edit,
        .btn-action-edit {
            background: rgba(0, 86, 179, 0.06);
            color: var(--accent-blue);
            border-color: rgba(0, 86, 179, 0.15);
            margin-right: 0;
        }

        button.btn-edit:hover,
        a.btn-edit:hover,
        .btn-edit:hover,
        .btn-action-edit:hover {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            box-shadow: 0 4px 8px rgba(0, 86, 179, 0.15);
            transform: translateY(-1px);
        }

        button.btn-delete,
        a.btn-delete,
        .btn-delete,
        .btn-action-delete {
            background: rgba(239, 68, 68, 0.06);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.15);
        }

        button.btn-delete:hover,
        a.btn-delete:hover,
        .btn-delete:hover,
        .btn-action-delete:hover {
            background: var(--error-red);
            color: #fff;
            border-color: var(--error-red);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.15);
            transform: translateY(-1px);
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
