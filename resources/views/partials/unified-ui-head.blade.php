<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
    :root {
        color-scheme: light;
        --ui-bg: #f6f0e6;
        --ui-surface: #fffdf9;
        --ui-surface-muted: #f7f1e8;
        --ui-line: #dfd3c3;
        --ui-line-strong: #cbbba6;
        --ui-text: #1f2933;
        --ui-muted: #667281;
        --ui-primary: #0f766e;
        --ui-primary-strong: #115e59;
        --ui-accent: #c27b34;
        --ui-accent-soft: rgba(194, 123, 52, 0.12);
        --ui-shadow: 0 20px 48px rgba(31, 41, 51, 0.08);
        --ui-radius: 28px;
        --ui-radius-sm: 18px;
    }

    * {
        box-sizing: border-box;
    }

    body.ui-body {
        min-height: 100vh;
        margin: 0;
        font-family: "Plus Jakarta Sans", sans-serif;
        color: var(--ui-text);
        background:
            radial-gradient(circle at top left, rgba(15, 118, 110, 0.10), transparent 30%),
            radial-gradient(circle at top right, rgba(194, 123, 52, 0.12), transparent 28%),
            linear-gradient(180deg, #fbf8f2 0%, var(--ui-bg) 100%);
    }

    .ui-shell {
        width: min(1180px, calc(100% - 32px));
        margin: 0 auto;
        padding: 32px 0 48px;
    }

    .ui-header-card,
    .ui-card,
    .ui-stat-card,
    .ui-table-card,
    .ui-log-item {
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius);
        background: var(--ui-surface);
        box-shadow: var(--ui-shadow);
    }

    .ui-header-card {
        padding: 28px;
        background: linear-gradient(135deg, var(--ui-surface) 0%, #f4ebdf 100%);
    }

    .ui-card,
    .ui-table-card {
        padding: 22px;
    }

    .ui-stat-card {
        padding: 20px;
        border-radius: 24px;
        background: linear-gradient(135deg, var(--ui-surface) 0%, var(--ui-surface-muted) 100%);
    }

    .ui-stat-card--accent {
        background: linear-gradient(135deg, rgba(194, 123, 52, 0.14) 0%, rgba(255, 253, 249, 0.98) 100%);
    }

    .ui-stat-card--primary {
        background: linear-gradient(135deg, rgba(15, 118, 110, 0.10) 0%, rgba(255, 253, 249, 0.98) 100%);
    }

    .ui-kicker {
        margin: 0 0 10px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: var(--ui-primary);
    }

    .ui-title {
        margin: 0;
        font-size: clamp(2rem, 3.2vw, 3.4rem);
        line-height: 1.05;
        letter-spacing: -0.04em;
    }

    .ui-subtitle,
    .ui-muted {
        color: var(--ui-muted);
    }

    .ui-subtitle {
        margin: 14px 0 0;
        max-width: 60rem;
        font-size: 0.98rem;
        line-height: 1.7;
    }

    .ui-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .ui-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 999px;
        border: 1px solid transparent;
        background: var(--ui-primary);
        color: #fff;
        text-decoration: none;
        font-size: 0.92rem;
        font-weight: 700;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .ui-button:hover {
        background: var(--ui-primary-strong);
        transform: translateY(-1px);
    }

    .ui-button--secondary {
        background: rgba(255, 255, 255, 0.78);
        border-color: var(--ui-line);
        color: var(--ui-text);
    }

    .ui-button--secondary:hover {
        background: var(--ui-surface-muted);
        border-color: var(--ui-line-strong);
    }

    .ui-button--accent {
        background: var(--ui-accent);
    }

    .ui-button--accent:hover {
        background: #a96427;
    }

    .ui-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid rgba(194, 123, 52, 0.32);
        background: var(--ui-accent-soft);
        color: #9a5d1d;
        padding: 10px 16px;
        font-weight: 700;
    }

    .ui-mono {
        font-family: "JetBrains Mono", monospace;
    }

    .ui-section-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .ui-section-copy {
        margin: 8px 0 0;
        color: var(--ui-muted);
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .ui-table-card {
        overflow: hidden;
        padding: 0;
    }

    .ui-table-wrap {
        overflow-x: auto;
    }

    .ui-data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ui-data-table th,
    .ui-data-table td {
        padding: 16px 22px;
        text-align: left;
        border-bottom: 1px solid rgba(223, 211, 195, 0.85);
    }

    .ui-data-table th {
        background: var(--ui-surface-muted);
        color: var(--ui-muted);
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .ui-data-table tbody tr:hover {
        background: rgba(15, 118, 110, 0.04);
    }

    .ui-log-list {
        display: grid;
        gap: 10px;
        margin: 16px 0 0;
        padding: 0;
        list-style: none;
    }

    .ui-log-item {
        padding: 14px 16px;
        border-radius: var(--ui-radius-sm);
        background: linear-gradient(135deg, var(--ui-surface) 0%, var(--ui-surface-muted) 100%);
    }

    .ui-log-item--empty {
        color: var(--ui-muted);
    }

    .ui-log-meta {
        margin: 0;
        color: var(--ui-primary);
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1.6;
    }

    .ui-log-text {
        margin: 6px 0 0;
        color: var(--ui-text);
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .ui-back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--ui-muted);
        font-size: 0.92rem;
        font-weight: 600;
        text-decoration: none;
    }

    .ui-back-link:hover {
        color: var(--ui-primary);
    }

    @media (max-width: 720px) {
        .ui-shell {
            width: min(100% - 24px, 1180px);
            padding-top: 22px;
        }
    }
</style>
