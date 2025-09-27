(function (window, document) {
    'use strict';

    if (!window || !document || typeof window.hrSaOgPreview === 'undefined') {
        return;
    }

    var data = window.hrSaOgPreview;
    var container = document.querySelector('[data-hr-sa-og-preview]');

    if (!container) {
        return;
    }

    var select = container.querySelector('#hr_sa_og_preview_target');
    var statusEl = container.querySelector('.hr-sa-og-preview__status');
    var tableBody = container.querySelector('[data-hr-sa-og-preview-table]');
    var imageFigure = container.querySelector('[data-hr-sa-og-preview-image]');
    var imageEl = imageFigure ? imageFigure.querySelector('img') : null;
    var placeholderImage = typeof data.placeholderImage === 'string' ? data.placeholderImage : '';
    var tableConfig = Array.isArray(data.table) ? data.table : [];
    var targets = Array.isArray(data.targets) ? data.targets : [];

    function formatOption(label, type) {
        var template = data.strings && data.strings.optionFormat ? data.strings.optionFormat : '%1$s — %2$s';
        return template.replace('%1$s', label).replace('%2$s', type);
    }

    if (select && !select.options.length && targets.length) {
        targets.forEach(function (item) {
            var option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = formatOption(item.label, item.type);
            select.appendChild(option);
        });
    }

    if (imageEl && placeholderImage) {
        imageEl.addEventListener('error', function () {
            if (placeholderImage && imageEl.src !== placeholderImage) {
                imageEl.src = placeholderImage;
            }
        });
    }

    function clean(value) {
        return typeof value === 'string' ? value : '';
    }

    function setStatus(message) {
        if (!statusEl) {
            return;
        }

        statusEl.textContent = message || '';
    }

    function setLoadingState(isLoading) {
        container.classList.toggle('is-loading', Boolean(isLoading));
        container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function ensurePlaceholder(image, source, altText) {
        if (!image) {
            return;
        }

        if (source) {
            image.src = source;
        } else if (placeholderImage) {
            image.src = placeholderImage;
        } else {
            image.removeAttribute('src');
        }

        image.alt = altText || (data.strings && data.strings.imageAlt ? data.strings.imageAlt : '');
    }

    function resolveImageSource(snapshot) {
        var fields = snapshot.fields || {};
        var og = snapshot.og || {};
        var twitter = snapshot.twitter || {};

        return clean(fields.image) || clean(og['og:image']) || clean(twitter['twitter:image']);
    }

    function updateImage(snapshot) {
        if (!imageEl) {
            return;
        }

        var fields = snapshot.fields || {};
        var imageSource = resolveImageSource(snapshot);
        var altText = clean(fields.title) || clean(fields.site_name) || (data.strings && data.strings.imageAlt ? data.strings.imageAlt : '');

        ensurePlaceholder(imageEl, imageSource, altText);

        if (imageFigure) {
            imageFigure.classList.toggle('has-image', Boolean(imageSource));
        }
    }

    function updateTable(fields) {
        if (!tableBody) {
            return;
        }

        while (tableBody.firstChild) {
            tableBody.removeChild(tableBody.firstChild);
        }

        if (!tableConfig.length) {
            return;
        }

        tableConfig.forEach(function (row) {
            var key = row.key;
            var label = row.label;
            var value = clean(fields[key]);
            var display = value || (data.strings && data.strings.notSet ? data.strings.notSet : '');

            var tr = document.createElement('tr');
            var th = document.createElement('th');
            var td = document.createElement('td');

            th.scope = 'row';
            th.textContent = label;
            td.textContent = display;

            tr.appendChild(th);
            tr.appendChild(td);
            tableBody.appendChild(tr);
        });
    }

    var activeRequest = 0;

    function fetchPreview(target) {
        var requestId = ++activeRequest;
        setLoadingState(true);
        setStatus(data.strings && data.strings.loading ? data.strings.loading : '');

        var body = new window.URLSearchParams();
        body.append('action', 'hr_sa_open_graph_preview');
        body.append('nonce', data.nonce || '');
        body.append('target', String(target));

        window.fetch(data.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('request_failed');
            }

            return response.json();
        }).then(function (payload) {
            if (requestId !== activeRequest) {
                return;
            }

            if (!payload || !payload.success || !payload.data) {
                throw new Error('request_failed');
            }

            var result = payload.data;
            updateImage(result);
            updateTable(result.fields || {});

            var messages = [];
            if (result.blocked && data.strings && data.strings.blocked) {
                messages.push(data.strings.blocked);
            }

            if (!result.og_enabled && data.strings && data.strings.ogDisabled) {
                messages.push(data.strings.ogDisabled);
            }

            if (!result.twitter_enabled && data.strings && data.strings.twitterDisabled) {
                messages.push(data.strings.twitterDisabled);
            }

            if (!messages.length && data.strings && data.strings.ready) {
                messages.push(data.strings.ready);
            }

            setStatus(messages.join(' '));
        }).catch(function () {
            if (requestId !== activeRequest) {
                return;
            }

            setStatus(data.strings && data.strings.error ? data.strings.error : '');
        }).finally(function () {
            if (requestId !== activeRequest) {
                return;
            }

            setLoadingState(false);
        });
    }

    if (select) {
        select.addEventListener('change', function () {
            fetchPreview(select.value);
        });
    }

    var defaultTarget = typeof data.defaultTarget !== 'undefined' ? data.defaultTarget : (select ? select.value : 0);
    if (select) {
        select.value = String(defaultTarget);
    }

    fetchPreview(defaultTarget);
})(window, document);
