(() => {
    const wpGlobal = window.wp || {};
    const __ = wpGlobal.i18n && typeof wpGlobal.i18n.__ === 'function'
        ? wpGlobal.i18n.__
        : (text) => text;

    const config = window.hrSaJsonLdPreview || null;
    if (!config || typeof config !== 'object') {
        return;
    }

    const root = document.querySelector('[data-hr-sa-jsonld-preview]');
    if (!root) {
        return;
    }

    const select = root.querySelector('[data-hr-sa-jsonld-preview-select]');
    const status = root.querySelector('[data-hr-sa-jsonld-preview-status]');
    const tableContainer = root.querySelector('[data-hr-sa-jsonld-preview-table]');
    const jsonField = root.querySelector('[data-hr-sa-jsonld-preview-json]');

    const targets = Array.isArray(config.targets) ? config.targets : [];
    const defaultTarget = typeof config.defaultTarget === 'number'
        ? String(config.defaultTarget)
        : String(config.defaultTarget || '0');

    const getMessage = (key, fallback) => {
        if (!config.messages || typeof config.messages !== 'object') {
            return fallback;
        }
        const value = config.messages[key];
        return typeof value === 'string' && value !== '' ? value : fallback;
    };

    const setStatus = (message, state = '') => {
        if (!status) {
            return;
        }

        const text = typeof message === 'string' ? message : '';
        status.textContent = text;
        if (text === '') {
            status.setAttribute('hidden', 'hidden');
        } else {
            status.removeAttribute('hidden');
        }

        if (state) {
            status.setAttribute('data-state', state);
        } else {
            status.removeAttribute('data-state');
        }
    };

    const clearTable = () => {
        if (tableContainer) {
            tableContainer.innerHTML = '';
        }
    };

    const renderJson = (content) => {
        if (!jsonField) {
            return;
        }

        const text = typeof content === 'string' ? content : '';
        jsonField.value = text !== '' ? text : getMessage('jsonEmpty', '');
    };

    const renderRows = (rows) => {
        clearTable();

        if (!Array.isArray(rows) || rows.length === 0) {
            setStatus(getMessage('empty', __('No JSON-LD nodes were generated for this selection.', 'hr-seo-assistant')), 'info');
            return;
        }

        const table = document.createElement('table');
        table.className = 'widefat hr-sa-jsonld-preview__table-grid';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');

        const thKey = document.createElement('th');
        thKey.scope = 'col';
        thKey.textContent = getMessage('tableKey', __('Property', 'hr-seo-assistant'));

        const thValue = document.createElement('th');
        thValue.scope = 'col';
        thValue.textContent = getMessage('tableValue', __('Value', 'hr-seo-assistant'));

        headerRow.appendChild(thKey);
        headerRow.appendChild(thValue);
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        rows.forEach((row) => {
            if (!row || typeof row !== 'object') {
                return;
            }

            const label = typeof row.label === 'string' ? row.label : '';
            const value = typeof row.value === 'string' ? row.value : '';

            const tr = document.createElement('tr');

            const th = document.createElement('th');
            th.scope = 'row';
            th.textContent = label;

            const td = document.createElement('td');
            td.textContent = value;

            tr.appendChild(th);
            tr.appendChild(td);
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);

        if (tableContainer) {
            tableContainer.appendChild(table);
        }

        setStatus('', 'success');
    };

    const populateTargets = () => {
        if (!select || targets.length === 0) {
            return;
        }

        select.innerHTML = '';

        targets.forEach((target) => {
            if (!target || typeof target !== 'object') {
                return;
            }

            const value = typeof target.id === 'number' || typeof target.id === 'string'
                ? String(target.id)
                : '';

            if (value === '') {
                return;
            }

            const label = typeof target.label === 'string' ? target.label : value;
            const type  = typeof target.type === 'string' && target.type !== '' ? target.type : '';

            const option = document.createElement('option');
            option.value = value;
            option.textContent = type !== '' ? `${label} (${type})` : label;
            if (value === defaultTarget) {
                option.selected = true;
            }

            select.appendChild(option);
        });

        if (!select.querySelector('option[selected]') && select.options.length > 0) {
            select.options[0].selected = true;
        }
    };

    let activeController = null;

    const loadPreview = (targetValue) => {
        const ajaxUrl = typeof config.ajaxUrl === 'string' ? config.ajaxUrl : '';
        const nonce = typeof config.nonce === 'string' ? config.nonce : '';

        if (!ajaxUrl) {
            setStatus(__('The AJAX endpoint is not available.', 'hr-seo-assistant'), 'error');
            return;
        }

        if (activeController && typeof activeController.abort === 'function') {
            activeController.abort();
        }

        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        activeController = controller;

        setStatus(getMessage('loading', __('Loading preview…', 'hr-seo-assistant')), 'loading');
        clearTable();
        renderJson('');

        const params = new URLSearchParams();
        params.append('action', 'hr_sa_jsonld_preview');
        params.append('nonce', nonce);
        params.append('target', String(targetValue));

        const fetchOptions = {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        };

        if (controller && controller.signal) {
            fetchOptions.signal = controller.signal;
        }

        window.fetch(ajaxUrl, fetchOptions)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('request_failed');
                }
                return response.json();
            })
            .then((payload) => {
                if (controller && activeController !== controller) {
                    return;
                }

                if (!payload || typeof payload !== 'object') {
                    throw new Error('invalid_response');
                }

                if (!payload.success) {
                    const message = payload.data && typeof payload.data.message === 'string'
                        ? payload.data.message
                        : getMessage('error', __('We could not load the preview. Please try again.', 'hr-seo-assistant'));
                    throw new Error(message);
                }

                const data = payload.data || {};
                renderRows(data.rows);
                renderJson(data.json);

                if (data.rows && Array.isArray(data.rows) && data.rows.length === 0) {
                    setStatus(getMessage('empty', __('No JSON-LD nodes were generated for this selection.', 'hr-seo-assistant')), 'info');
                }
            })
            .catch((error) => {
                if (controller && typeof error === 'object' && error.name === 'AbortError') {
                    return;
                }

                const message = typeof error === 'string'
                    ? error
                    : (error && typeof error.message === 'string' && error.message !== 'request_failed' && error.message !== 'invalid_response'
                        ? error.message
                        : getMessage('error', __('We could not load the preview. Please try again.', 'hr-seo-assistant')));
                setStatus(message, 'error');
                clearTable();
                renderJson('');
            })
            .finally(() => {
                if (activeController === controller) {
                    activeController = null;
                }
            });
    };

    if (jsonField) {
        jsonField.value = getMessage('jsonEmpty', '');
    }

    populateTargets();

    const initialMessage = getMessage('ready', __('Select an item to load its JSON-LD.', 'hr-seo-assistant'));
    setStatus(initialMessage, 'info');

    if (select) {
        select.addEventListener('change', (event) => {
            const value = event.target && typeof event.target.value === 'string'
                ? event.target.value
                : defaultTarget;
            loadPreview(value);
        });
    }

    const initialValue = select && typeof select.value === 'string' && select.value !== ''
        ? select.value
        : defaultTarget;

    loadPreview(initialValue);
})();
