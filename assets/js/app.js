(() => {
    const showLoader = () => {
        document.body.classList.add('is-loading');
    };

    window.addEventListener('pageshow', () => {
        document.body.classList.remove('is-loading');
    });

    const menuToggle = document.querySelector('[data-menu-toggle]');
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            const isOpen = document.body.classList.toggle('nav-open');
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    document.querySelectorAll('[data-bouquet-toggle]').forEach((toggle) => {
        const card = toggle.closest('.bouquet-card');
        const sync = () => {
            if (!card) return;
            card.classList.toggle('enabled', toggle.checked);
        };
        toggle.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-copy-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            const field = button.closest('.copy-field');
            const input = field ? field.querySelector('[data-copy-source]') : null;
            if (!input) return;
            input.select();
            input.setSelectionRange(0, input.value.length);
            try {
                await navigator.clipboard.writeText(input.value);
                button.textContent = 'Copied';
                setTimeout(() => { button.textContent = 'Copy'; }, 1600);
            } catch (error) {
                document.execCommand('copy');
                button.textContent = 'Copied';
                setTimeout(() => { button.textContent = 'Copy'; }, 1600);
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                const loadingText = button.getAttribute('data-loading-text');
                if (loadingText) button.textContent = loadingText;
                button.disabled = true;
            }
            form.classList.add('is-loading');
            showLoader();
        });
    });

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented) return;
            if (event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.target && link.target !== '_self') return;
            const href = link.getAttribute('href') || '';
            if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return;
            showLoader();
        });
    });
})();