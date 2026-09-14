    <script>
    (() => {
        const shell = document.querySelector('[data-shell]');
        if (!shell) return;

        // Standard web breakpoint only — not device/Flutter profiles.
        const drawerQuery = window.matchMedia('(max-width: 959px)');

        const isMobile = () => drawerQuery.matches;

        const setDrawerOpen = (open) => {
            // .is-collapsed = drawer closed on mobile. Desktop ignores this class in CSS.
            const collapsed = !open;
            shell.classList.toggle('is-collapsed', collapsed);
            shell.dataset.collapsed = collapsed ? '1' : '0';
            shell.setAttribute('data-mode', isMobile() ? 'drawer' : 'layout');
        };

        const syncToViewport = () => {
            if (isMobile()) {
                setDrawerOpen(false); // hamburger closed by default
                return;
            }
            // Desktop: sidebar always part of layout
            setDrawerOpen(true);
            // Drop legacy desktop-collapse preference if present
            try {
                localStorage.removeItem('wd.sidebarCollapsed');
            } catch (_) { /* ignore */ }
        };

        const toggleDrawer = () => {
            if (!isMobile()) return;
            setDrawerOpen(shell.dataset.collapsed === '1');
        };

        const closeDrawer = () => {
            if (!isMobile()) return;
            setDrawerOpen(false);
        };

        syncToViewport();

        if (typeof drawerQuery.addEventListener === 'function') {
            drawerQuery.addEventListener('change', syncToViewport);
        } else if (typeof drawerQuery.addListener === 'function') {
            drawerQuery.addListener(syncToViewport);
        }

        document.querySelectorAll('[data-shell-toggle]').forEach((btn) => {
            btn.addEventListener('click', toggleDrawer);
        });
        document.querySelectorAll('[data-shell-close]').forEach((btn) => {
            btn.addEventListener('click', closeDrawer);
        });
        document.querySelector('[data-shell-backdrop]')?.addEventListener('click', closeDrawer);
    })();
    </script>
