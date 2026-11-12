(function () {
    'use strict';

    document.querySelectorAll('[data-vendor-key-control]').forEach(function (control) {
        var form = control.querySelector('[data-reveal-form]');
        var button = control.querySelector('[data-show-key]');
        var panel = control.querySelector('[data-key-panel]');
        var input = control.querySelector('[data-key-value]');
        var status = control.querySelector('[data-key-status]');

        function hideKey() {
            input.value = '';
            panel.hidden = true;
            button.textContent = 'Show API key';
            status.textContent = '';
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!panel.hidden) {
                hideKey();
                return;
            }
            button.disabled = true;
            status.textContent = 'Loading API key…';
            try {
                var response = await fetch(form.action, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)),
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                var result = await response.json();
                if (result.csrf) {
                    // Reveal requests rotate the session's CSRF token. Keep the other
                    // vendor-edit and key-regeneration forms usable afterward.
                    document.querySelectorAll('input[type="hidden"]').forEach(function (field) {
                        if (field.name === result.csrf.name) {
                            field.value = result.csrf.hash;
                        }
                    });
                }
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to display the API key. Refresh and try again.');
                }
                input.value = result.apiKey;
                panel.hidden = false;
                button.textContent = 'Hide API key';
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message || 'Unable to display the API key. Refresh and try again.';
            } finally {
                button.disabled = false;
            }
        });

        control.querySelector('[data-copy-key]').addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(input.value);
                status.textContent = 'API key copied.';
            } catch (error) {
                input.focus();
                input.select();
                status.textContent = 'Key selected. Press Ctrl+C to copy.';
            }
        });
        window.addEventListener('pagehide', hideKey);
    });
}());
