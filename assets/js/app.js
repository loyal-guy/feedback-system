/* FeedbackHub client-side script.
 * Kept in an external file because the Content-Security-Policy blocks inline scripts.
 * Client-side checks only improve usability; the server validates everything again. */
(function () {
    'use strict';

    // Mobile navigation toggle
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Password strength meter
    document.querySelectorAll('[data-strength]').forEach(function (input) {
        var bar = document.getElementById(input.getAttribute('data-strength'));
        if (!bar) return;
        input.addEventListener('input', function () {
            var v = input.value, score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            bar.className = 'strength-bar' + (v ? ' s' + score : '');
        });
    });

    // Show / hide password
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });

    // Character counters
    document.querySelectorAll('[data-counter]').forEach(function (input) {
        var out = document.getElementById(input.getAttribute('data-counter'));
        var max = input.getAttribute('maxlength');
        var update = function () { out.textContent = input.value.length + ' / ' + max; };
        input.addEventListener('input', update);
        update();
    });

    // Confirmation for dangerous actions
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) ev.preventDefault();
        });
    });

    // Auto-submit filter selects
    document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // Charts (admin dashboard). Data is passed through data-* attributes.
    if (window.Chart) {
        var palette = ['#0f5c55', '#f2a541', '#4f86c6', '#c45a5a', '#7b9e3e', '#8e6bb8'];
        Chart.defaults.font.family = 'Segoe UI, system-ui, sans-serif';
        Chart.defaults.color = '#5f6b6d';
        document.querySelectorAll('canvas[data-chart]').forEach(function (c) {
            var type = c.getAttribute('data-chart');
            var labels = JSON.parse(c.getAttribute('data-labels') || '[]');
            var values = JSON.parse(c.getAttribute('data-values') || '[]');
            var label = c.getAttribute('data-label') || '';
            var ds = { label: label, data: values, borderWidth: 2 };
            if (type === 'line') {
                ds.borderColor = palette[0]; ds.backgroundColor = 'rgba(15,92,85,.12)'; ds.fill = true; ds.tension = .3;
            } else if (type === 'doughnut') {
                ds.backgroundColor = palette; ds.borderColor = '#fff';
            } else {
                ds.backgroundColor = c.getAttribute('data-color') || palette[0]; ds.borderRadius = 6; ds.borderWidth = 0;
            }
            new Chart(c, {
                type: type,
                data: { labels: labels, datasets: [ds] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
                    scales: type === 'doughnut' ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }
                }
            });
        });
    }
})();
