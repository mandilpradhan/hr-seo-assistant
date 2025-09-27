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

    var cards = Array.prototype.slice.call(container.querySelectorAll('.hr-sa-og-card')).map(function (element) {
        var imageEl = element.querySelector('.hr-sa-og-card__image');
        if (imageEl && placeholderImage) {
            imageEl.addEventListener('error', function () {
                if (imageEl.src !== placeholderImage) {
                    imageEl.src = placeholderImage;
                }
            });
        }

        return {
            element: element,
            platform: element.getAttribute('data-platform') || '',
            image: imageEl,
            title: element.querySelector('.hr-sa-og-card__title'),
            description: element.querySelector('.hr-sa-og-card__description'),
            site: element.querySelector('.hr-sa-og-card__site'),
            url: element.querySelector('.hr-sa-og-card__url')
        };
    });

    function clean(value) {
        return typeof value === 'string' ? value : '';
    }

    function formatUrl(url) {
        var cleanUrl = clean(url);
        if (!cleanUrl) {
            return '';
        }

        try {
            var parsed = new window.URL(cleanUrl);
            return parsed.host + parsed.pathname;
        } catch (error) {
            return cleanUrl.replace(/^https?:\/\//i, '');
        }
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

    function ensurePlaceholder(imageEl, source) {
        if (!imageEl) {
            return;
        }

        if (source) {
            imageEl.src = source;
        } else if (placeholderImage) {
            imageEl.src = placeholderImage;
        } else {
            imageEl.removeAttribute('src');
        }

        imageEl.alt = data.strings && data.strings.imageAlt ? data.strings.imageAlt : '';
    }

    function updateCard(card, payload) {
        if (!card) {
            return;
        }

        var titleValue = clean(payload.title);
        var descriptionValue = clean(payload.description);
        var siteValue = clean(payload.site);
        var urlValue = clean(payload.url);
        var imageValue = clean(payload.image);

        if (card.title) {
            card.title.textContent = titleValue || (data.strings && data.strings.notSet ? data.strings.notSet : '');
        }

        if (card.description) {
            card.description.textContent = descriptionValue || (data.strings && data.strings.notSet ? data.strings.notSet : '');
        }

        if (card.site) {
            card.site.textContent = siteValue || (data.strings && data.strings.notSet ? data.strings.notSet : '');
        }

        if (card.url) {
            card.url.textContent = urlValue ? formatUrl(urlValue) : (data.strings && data.strings.notSet ? data.strings.notSet : '');
        }

        if (card.image) {
            ensurePlaceholder(card.image, imageValue || '');
        }
    }

    function updateCards(snapshot) {
        var fields = snapshot.fields || {};
        var og = snapshot.og || {};
        var twitter = snapshot.twitter || {};

        var baseTitle = clean(fields.title);
        var baseDescription = clean(fields.description);
        var baseUrl = clean(fields.url);
        var baseImage = clean(fields.image);
        var baseSite = clean(fields.site_name);
        var twitterHandle = clean(fields.twitter_handle);
        var twitterTitle = clean(twitter['twitter:title']) || baseTitle;
        var twitterDescription = clean(twitter['twitter:description']) || baseDescription;
        var twitterImage = clean(twitter['twitter:image']) || baseImage;
        var cardType = clean(twitter['twitter:card']);

        cards.forEach(function (card) {
            var payload = {
                title: baseTitle,
                description: baseDescription,
                site: baseSite,
                url: baseUrl,
                image: baseImage
            };

            if (card.platform === 'twitter') {
                payload.title = twitterTitle;
                payload.description = twitterDescription;
                payload.image = twitterImage;
                payload.site = twitterHandle || baseSite || cardType;
            }

            updateCard(card, payload);
        });
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
            updateCards(result);
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

            if (result.twitter && result.twitter['twitter:card'] && data.strings && data.strings.cardType) {
                messages.push(data.strings.cardType.replace('%s', result.twitter['twitter:card']));
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
