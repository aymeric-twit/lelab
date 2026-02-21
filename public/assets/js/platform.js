// SEO Platform - Main JS

document.addEventListener('DOMContentLoaded', function () {
    // Sidebar toggle (mobile)
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function (e) {
            if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

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
});
