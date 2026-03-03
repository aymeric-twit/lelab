// SEO Platform - Main JS

document.addEventListener('DOMContentLoaded', function () {
    // CSRF token for AJAX requests
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const token = csrfMeta.getAttribute('content');

        // Intercept fetch
        const originalFetch = window.fetch;
        window.fetch = function (url, options) {
            options = options || {};
            if (options.method && options.method.toUpperCase() !== 'GET') {
                options.headers = options.headers || {};
                if (options.headers instanceof Headers) {
                    options.headers.set('X-CSRF-TOKEN', token);
                } else {
                    options.headers['X-CSRF-TOKEN'] = token;
                }
            }
            return originalFetch.call(this, url, options);
        };

        // Intercept XMLHttpRequest
        const originalXHROpen = XMLHttpRequest.prototype.open;
        const originalXHRSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method) {
            this._method = method;
            return originalXHROpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
            if (this._method && this._method.toUpperCase() !== 'GET') {
                this.setRequestHeader('X-CSRF-TOKEN', token);
            }
            return originalXHRSend.apply(this, arguments);
        };
    }

    // Sélecteur de langue dans le header
    const langSelector = document.getElementById('platformLangSelector');
    if (langSelector) {
        // Initialiser la langue active depuis PLATFORM_LANG ou localStorage
        const langueStockee = localStorage.getItem('platformLang');
        const langueActive = window.PLATFORM_LANG || langueStockee || 'fr';

        // Marquer le bouton actif
        langSelector.querySelectorAll('.btn-lang').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === langueActive);
            btn.addEventListener('click', function () {
                const lang = this.getAttribute('data-lang');

                // Mettre à jour le bouton actif
                langSelector.querySelectorAll('.btn-lang').forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-lang') === lang);
                });

                // Stocker dans localStorage
                localStorage.setItem('platformLang', lang);

                // Mettre à jour le query param ?lg=
                var url = new URL(window.location.href);
                url.searchParams.set('lg', lang);
                window.PLATFORM_LANG = lang;

                // Émettre un événement custom pour les plugins
                document.dispatchEvent(new CustomEvent('platformLangChange', { detail: { lang: lang } }));

                // Recharger la page avec le nouveau paramètre lg
                window.location.href = url.toString();
            });
        });
    }
});
