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

    // Toasts — auto-show on page load
    var toastContainer = document.getElementById('toastContainer');
    if (toastContainer) {
        toastContainer.querySelectorAll('.toast').forEach(function (el, i) {
            setTimeout(function () { new bootstrap.Toast(el).show(); }, i * 200);
        });
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

// Fonction globale pour créer un toast programmatiquement (utilisable par les plugins)
window.afficherToast = function (message, type) {
    type = type || 'info';
    var container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1090';
        document.body.appendChild(container);
    }

    var cfg = {
        success: { icone: 'bi-check-circle-fill', classe: 'toast-success', titre: 'Succès' },
        danger:  { icone: 'bi-x-circle-fill',     classe: 'toast-danger',  titre: 'Erreur' },
        warning: { icone: 'bi-exclamation-triangle-fill', classe: 'toast-warning', titre: 'Attention' },
        info:    { icone: 'bi-info-circle-fill',   classe: 'toast-info',    titre: 'Information' }
    };
    var c = cfg[type] || cfg.info;

    var html = '<div class="toast ' + c.classe + '" role="alert" aria-live="assertive" aria-atomic="true">' +
        '<div class="toast-header">' +
        '<i class="bi ' + c.icone + ' me-2 toast-icon"></i>' +
        '<strong class="me-auto toast-title">' + c.titre + '</strong>' +
        '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Fermer"></button>' +
        '</div>' +
        '<div class="toast-body">' + message + '</div>' +
        '<div class="toast-progress"><div class="toast-progress-bar"></div></div>' +
        '</div>';

    container.insertAdjacentHTML('beforeend', html);
    var toastEl = container.lastElementChild;
    var toast = new bootstrap.Toast(toastEl, { autohide: true, delay: 5000 });
    toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
    toast.show();
};
