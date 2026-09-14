{{-- Shared shell tokens; works without Vite build (tests / first boot). --}}
<style>
    :root {
        --shell-bg: #f3f6f1;
        --shell-card: #ffffff;
        --shell-ink: #1c2a1f;
        --shell-muted: #5b6b5e;
        --shell-accent: #2f6b4f;
        --shell-accent-dark: #24553e;
        --shell-border: #d5e0d6;
        --shell-danger: #9b2c2c;
        --shell-sidebar: #1e3d2f;
        --shell-sidebar-width: 16.5rem;

        /* Short aliases used by Livewire dashboard views */
        --bg: var(--shell-bg);
        --card: var(--shell-card);
        --ink: var(--shell-ink);
        --muted: var(--shell-muted);
        --accent: var(--shell-accent);
        --accent-dark: var(--shell-accent-dark);
        --border: var(--shell-border);
        --danger: var(--shell-danger);
    }
    * { box-sizing: border-box; }
    html {
        accent-color: var(--shell-accent);
        color-scheme: light;
    }
    body.shell-body {
        margin: 0;
        min-height: 100vh;
        font-family: "Instrument Sans", "Segoe UI", Tahoma, sans-serif;
        color: var(--shell-ink);
        background: var(--shell-bg);
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }
    /* Consistent form controls across Chrome / Cursor Simple Browser */
    body.shell-body input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="file"]):not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]),
    body.shell-body select,
    body.shell-body textarea {
        font: inherit;
        color: var(--shell-ink);
        background-color: #fff;
        border: 1px solid var(--shell-border);
        border-radius: 8px;
        padding: 0.55rem 0.7rem;
        max-width: 100%;
    }
    body.shell-body input:not([type="checkbox"]):not([type="radio"]):focus,
    body.shell-body select:focus,
    body.shell-body textarea:focus {
        outline: 2px solid color-mix(in srgb, var(--shell-accent) 35%, transparent);
        outline-offset: 1px;
        border-color: var(--shell-accent);
    }
    body.shell-body input[type="checkbox"],
    body.shell-body input[type="radio"] {
        accent-color: var(--shell-accent);
        width: 1rem;
        height: 1rem;
    }
    body.shell-body select {
        appearance: auto;
    }
    body.shell-body table {
        border-collapse: collapse;
    }
    body.shell-body h1,
    body.shell-body h2,
    body.shell-body h3 {
        line-height: 1.25;
        color: var(--shell-ink);
    }
    .shell {
        display: grid;
        grid-template-columns: var(--shell-sidebar-width) 1fr;
        min-height: 100vh;
        gap: 0;
    }
    /* Always out of flow — must not consume a desktop grid column. */
    .shell-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(28, 42, 31, 0.35);
        z-index: 35;
    }
    .shell-sidebar {
        background: var(--shell-sidebar);
        color: #e8f0ea;
        padding: 1rem 0.75rem 1.25rem;
        overflow: auto;
        position: sticky;
        top: 0;
        height: 100vh;
        grid-column: 1;
        grid-row: 1;
        margin: 0;
    }
    /* Desktop: hamburger + drawer close are mobile-only. */
    .shell-menu-toggle,
    .shell-drawer-close {
        display: none !important;
    }
    .shell-brand {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0.5rem;
        padding: 0.35rem 0.5rem 1rem;
        border-bottom: 1px solid rgba(255,255,255,.12);
        margin-bottom: 0.75rem;
    }
    .shell-brand a {
        color: #fff;
        text-decoration: none;
        font-weight: 700;
    }
    .shell-group {
        margin: 0.85rem 0 0.25rem;
        padding: 0 0.65rem;
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgba(232,240,234,.65);
    }
    .shell-nav a {
        display: block;
        color: #e8f0ea;
        text-decoration: none;
        border-radius: 8px;
        padding: 0.55rem 0.65rem;
        margin: 0.15rem 0;
    }
    .shell-nav a:hover { background: rgba(255,255,255,.08); }
    .shell-nav a.is-active {
        background: rgba(255,255,255,.14);
        font-weight: 600;
    }
    .shell-nav .is-stub::after {
        content: " soon";
        font-size: 0.7rem;
        opacity: 0.65;
    }
    .shell-main {
        display: flex;
        flex-direction: column;
        min-width: 0;
        grid-column: 2;
        grid-row: 1;
        margin: 0;
    }
    .shell-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 1.25rem;
        background: var(--shell-card);
        border-bottom: 1px solid var(--shell-border);
        position: sticky;
        top: 0;
        z-index: 20;
    }
    .shell-top-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
    .shell-breadcrumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
        color: var(--shell-muted);
        font-size: 0.92rem;
    }
    .shell-breadcrumbs a { color: var(--shell-accent); text-decoration: none; }
    .shell-top-right { display: flex; align-items: center; gap: 0.75rem; }
    .shell-notify-wrap { position: relative; }
    .shell-notify {
        border: 1px solid var(--shell-border);
        background: #f7faf7;
        color: var(--shell-muted);
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.85rem;
        cursor: pointer;
        font: inherit;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .shell-notify-badge {
        background: var(--shell-danger);
        color: #fff;
        border-radius: 999px;
        min-width: 1.15rem;
        padding: 0.05rem 0.35rem;
        font-size: 0.7rem;
        text-align: center;
        font-weight: 700;
    }
    .shell-notify-panel {
        position: absolute;
        right: 0;
        top: calc(100% + 0.35rem);
        width: min(22rem, 86vw);
        max-height: 22rem;
        overflow: auto;
        background: #fff;
        border: 1px solid var(--shell-border);
        border-radius: 10px;
        padding: 0.5rem;
        box-shadow: 0 10px 24px rgba(28,42,31,.1);
        z-index: 30;
    }
    .shell-notify-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.35rem 0.5rem;
        border-bottom: 1px solid var(--shell-border);
        margin-bottom: 0.35rem;
    }
    .shell-notify-item {
        padding: 0.55rem 0.4rem;
        border-radius: 8px;
    }
    .shell-notify-item.is-unread { background: #eef6f0; }
    .shell-notify-item-title { font-weight: 600; font-size: 0.88rem; }
    .shell-profile details { position: relative; }
    .shell-profile summary {
        list-style: none;
        cursor: pointer;
        border: 1px solid var(--shell-border);
        border-radius: 999px;
        padding: 0.4rem 0.8rem;
        background: #fff;
    }
    .shell-profile summary::-webkit-details-marker { display: none; }
    .shell-profile-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 0.35rem);
        min-width: 11rem;
        background: #fff;
        border: 1px solid var(--shell-border);
        border-radius: 10px;
        padding: 0.35rem;
        box-shadow: 0 10px 24px rgba(28,42,31,.1);
        z-index: 30;
    }
    .shell-profile-menu a,
    .shell-profile-menu button {
        display: block;
        width: 100%;
        text-align: left;
        border: 0;
        background: transparent;
        color: var(--shell-ink);
        text-decoration: none;
        padding: 0.55rem 0.7rem;
        border-radius: 8px;
        cursor: pointer;
        font: inherit;
    }
    .shell-profile-menu a:hover,
    .shell-profile-menu button:hover { background: #f0f4f0; }
    .shell-content { padding: 1.25rem 1.35rem 2rem; }
    .shell-empty-nav {
        margin: 0.75rem;
        padding: 0.75rem;
        border-radius: 8px;
        background: rgba(255,255,255,.08);
        color: rgba(232,240,234,.9);
        font-size: 0.9rem;
    }
    .card {
        background: var(--shell-card);
        border: 1px solid var(--shell-border);
        border-radius: 10px;
        padding: 1.25rem;
    }
    .muted { color: var(--shell-muted); }
    .btn {
        border: 1px solid var(--shell-border);
        background: #fff;
        color: var(--shell-ink);
        border-radius: 6px;
        padding: 0.25rem 0.55rem;
        cursor: pointer;
        text-decoration: none;
        font: inherit;
        font-size: 0.75rem;
        line-height: 1.25;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-sm {
        padding: 0.15rem 0.45rem;
        font-size: 0.6875rem;
    }
    .btn-primary {
        background: var(--shell-accent);
        border-color: var(--shell-accent);
        color: #fff;
    }
    .btn-ghost {
        background: transparent;
        border-color: rgba(255,255,255,.35);
        color: #fff;
    }
    .icon-btn {
        border: 1px solid var(--shell-border);
        background: #fff;
        border-radius: 8px;
        padding: 0.4rem 0.65rem;
        cursor: pointer;
    }
    .shell-footer {
        margin-top: auto;
        padding: 0.75rem 1.35rem 1.25rem;
        color: var(--shell-muted);
        font-size: 0.8rem;
    }
    .alert {
        border-radius: 8px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
    }
    .alert-error { background: #fde8e8; color: var(--shell-danger); border: 1px solid #f0c2c2; }
    .alert-info { background: #e8f2ec; color: var(--shell-ink); border: 1px solid var(--shell-border); }
    .skeleton {
        background: linear-gradient(90deg, #e7eee8, #f5f8f4, #e7eee8);
        background-size: 200% 100%;
        animation: shimmer 1.2s infinite;
        border-radius: 8px;
        min-height: 1rem;
    }
    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    @media (max-width: 959px) {
        .shell {
            grid-template-columns: 1fr;
        }
        .shell-main {
            grid-column: 1;
        }
        .shell-menu-toggle,
        .shell-drawer-close {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
        }
        .shell-brand {
            justify-content: space-between;
        }
        /* Drawer: off-canvas by default; open when not .is-collapsed */
        .shell-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: min(var(--shell-sidebar-width), 86vw);
            z-index: 40;
            transform: translateX(-105%);
            transition: transform .2s ease;
            grid-column: 1;
            grid-row: 1;
        }
        .shell:not(.is-collapsed) .shell-sidebar {
            transform: translateX(0);
        }
        .shell:not(.is-collapsed) .shell-backdrop {
            display: block;
        }
    }
</style>
