(() => {
    const wpGlobal = window.wp || {};
    const __ = wpGlobal.i18n && typeof wpGlobal.i18n.__ === 'function'
        ? wpGlobal.i18n.__
        : (text) => text;

    const selectors = {
        mediaButton: '.hr-sa-media-picker',
        copyButton: '.hr-sa-copy-json',
        localeSelect: '.hr-sa-locale-selector',
        fallbackInput: '#hr_sa_fallback_image',
        aiTestButton: '.hr-sa-ai-test',
        aiTestResult: '[data-hr-sa-ai-result]',
    };

    const aiSettings = window.hrSaAdminSettings || null;

    const normalizeToHttps = (rawUrl) => {
        if (typeof rawUrl !== 'string') {
            return '';
        }

        const trimmed = rawUrl.trim();
        if (trimmed === '') {
            return '';
        }

        if (/^https:\/\//i.test(trimmed)) {
            return trimmed;
        }

        if (/^http:\/\//i.test(trimmed)) {
            return trimmed.replace(/^http:\/\//i, 'https://');
        }

        if (/^\/\//.test(trimmed)) {
            return "https:" + trimmed;
        }

        return trimmed;
    };

    const updateFieldValue = (field, newValue, shouldDispatch) => {
        if (!field || typeof field.value === 'undefined') {
            return;
        }

        if (typeof newValue !== 'string') {
            return;
        }

        if (field.value === newValue) {
            return;
        }

        field.value = newValue;

        if (shouldDispatch) {
            try {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (error) {
                const changeEvent = document.createEvent('HTMLEvents');
                changeEvent.initEvent('change', true, false);
                field.dispatchEvent(changeEvent);
            }
        }
    };

    const resetCopyButton = (button) => {
        window.setTimeout(() => {
            button.removeAttribute('data-state');
            button.textContent = __('Copy Context & Settings JSON', 'hr-seo-assistant');
        }, 3000);
    };

    const fallbackCopy = (text, onSuccess, onFailure) => {
        const temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('readonly', '');
        temp.style.position = 'absolute';
        temp.style.left = '-9999px';
        temp.style.top = '0';
        temp.style.opacity = '0';
        document.body.appendChild(temp);

        const selection = document.getSelection();
        const previousRange = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

        temp.select();
        temp.setSelectionRange(0, temp.value.length);

        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        if (temp.parentNode) {
            temp.parentNode.removeChild(temp);
        }

        if (selection && previousRange) {
            selection.removeAllRanges();
            selection.addRange(previousRange);
        }

        if (copied) {
            onSuccess();
        } else {
            onFailure();
        }
    };

    const isSecureClipboardSupported = () => {
        const clipboard = navigator.clipboard;
        const hasClipboardApi = Boolean(clipboard && typeof clipboard.writeText === 'function');
        if (!hasClipboardApi) {
            return false;
        }

        if (typeof window.isSecureContext === 'boolean') {
            return window.isSecureContext;
        }

        return window.location && window.location.protocol === 'https:';
    };

    const copyWithClipboard = (text, onSuccess, onFailure) => {
        if (!isSecureClipboardSupported()) {
            fallbackCopy(text, onSuccess, onFailure);
            return;
        }

        let writeResult;
        try {
            writeResult = navigator.clipboard.writeText(text);
        } catch (error) {
            fallbackCopy(text, onSuccess, onFailure);
            return;
        }

        if (!writeResult || typeof writeResult.then !== 'function') {
            onSuccess();
            return;
        }

        writeResult.then(onSuccess).catch(() => {
            fallbackCopy(text, onSuccess, onFailure);
        });
    };

    const handleCopy = (button) => {
        const sourceId = button.getAttribute('data-source');
        if (!sourceId) {
            return;
        }

        const source = document.getElementById(sourceId);
        if (!source) {
            return;
        }

        const textToCopy = typeof source.value === 'string' && source.value !== ''
            ? source.value
            : source.textContent;

        if (typeof textToCopy !== 'string' || textToCopy === '') {
            return;
        }

        const onSuccess = () => {
            button.setAttribute('data-state', 'copied');
            button.textContent = __('Copied!', 'hr-seo-assistant');
            resetCopyButton(button);
        };

        const onFailure = () => {
            window.alert(__('Unable to copy. Please copy manually.', 'hr-seo-assistant'));
        };

        copyWithClipboard(textToCopy, onSuccess, onFailure);
    };

    const openMediaFrame = (targetId) => {
        if (!wpGlobal.media || typeof wpGlobal.media !== 'function') {
            window.alert(__('The media library is unavailable. Please reload the page and try again.', 'hr-seo-assistant'));
            return;
        }

        const frame = wpGlobal.media({
            title: __('Select fallback image', 'hr-seo-assistant'),
            library: { type: 'image' },
            button: { text: __('Use image', 'hr-seo-assistant') },
            multiple: false,
        });

        frame.on('select', () => {
            const inputField = document.getElementById(targetId);
            if (!inputField) {
                return;
            }

            const state = typeof frame.state === 'function' ? frame.state() : null;
            const selection = state && typeof state.get === 'function' ? state.get('selection') : null;
            const attachmentModel = selection && typeof selection.first === 'function' ? selection.first() : null;

            let url = '';
            if (attachmentModel && typeof attachmentModel.get === 'function') {
                url = attachmentModel.get('url') || '';
            }

            if (!url && attachmentModel && typeof attachmentModel.toJSON === 'function') {
                const attachmentData = attachmentModel.toJSON();
                if (attachmentData && typeof attachmentData.url === 'string') {
                    url = attachmentData.url;
                }
            }

            if (typeof url !== 'string' || url === '') {
                return;
            }

            const normalized = normalizeToHttps(url);
            updateFieldValue(inputField, normalized, true);
        });

        frame.open();
    };

    const bindMediaButtons = () => {
        const buttons = document.querySelectorAll(selectors.mediaButton);
        if (!buttons || buttons.length === 0) {
            return;
        }

        for (let index = 0; index < buttons.length; index += 1) {
            const button = buttons[index];
            if (!button) {
                continue;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = button.getAttribute('data-target');
                if (targetId) {
                    openMediaFrame(targetId);
                }
            });
        }
    };

    const bindCopyButtons = () => {
        const buttons = document.querySelectorAll(selectors.copyButton);
        if (!buttons || buttons.length === 0) {
            return;
        }

        for (let index = 0; index < buttons.length; index += 1) {
            const button = buttons[index];
            if (!button) {
                continue;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                handleCopy(button);
            });
        }
    };

    const bindFallbackNormalization = () => {
        const field = document.querySelector(selectors.fallbackInput);
        if (!field) {
            return;
        }

        const normalizeField = () => {
            const normalized = normalizeToHttps(field.value);
            updateFieldValue(field, normalized, false);
        };

        normalizeField();

        field.addEventListener('blur', normalizeField);
        field.addEventListener('change', normalizeField);
    };

    const setAiTestResult = (message, status) => {
        const resultNode = document.querySelector(selectors.aiTestResult);
        if (!resultNode) {
            if (status === 'error') {
                window.alert(message);
            }
            return;
        }

        if (typeof message === 'string') {
            resultNode.textContent = message;
        }

        if (status) {
            resultNode.setAttribute('data-state', status);
        } else {
            resultNode.removeAttribute('data-state');
        }
    };

    const bindAiTestButton = () => {
        if (!aiSettings || !selectors.aiTestButton) {
            return;
        }

        const button = document.querySelector(selectors.aiTestButton);
        if (!button) {
            return;
        }

        let isPending = false;

        const toggleButtonState = (loading) => {
            if (loading) {
                button.classList.add('hr-sa-btn-loading');
                button.setAttribute('disabled', 'disabled');
            } else {
                button.classList.remove('hr-sa-btn-loading');
                button.removeAttribute('disabled');
            }
        };

        button.addEventListener('click', (event) => {
            event.preventDefault();

            if (typeof window.fetch !== 'function') {
                window.alert(__('Your browser does not support the fetch API.', 'hr-seo-assistant'));
                return;
            }

            if (isPending) {
                return;
            }

            if (!aiSettings.aiEnabled) {
                const disabledMessage = aiSettings.messages && aiSettings.messages.disabled
                    ? aiSettings.messages.disabled
                    : __('Enable AI assistance before testing the connection.', 'hr-seo-assistant');
                setAiTestResult(disabledMessage, 'error');
                return;
            }

            if (!aiSettings.hasKey) {
                const missingKeyMessage = aiSettings.messages && aiSettings.messages.missingKey
                    ? aiSettings.messages.missingKey
                    : __('Add an API key before testing the connection.', 'hr-seo-assistant');
                setAiTestResult(missingKeyMessage, 'error');
                return;
            }

            isPending = true;
            toggleButtonState(true);

            const testingMessage = aiSettings.messages && aiSettings.messages.testing
                ? aiSettings.messages.testing
                : __('Testing connection…', 'hr-seo-assistant');
            setAiTestResult(testingMessage, 'pending');

            const params = new window.URLSearchParams();
            params.append('action', 'hr_sa_ai_test_connection');
            params.append('nonce', aiSettings.nonceTest);

            fetch(aiSettings.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: params.toString(),
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (payload && payload.success) {
                        const successMessage = payload.data && payload.data.message
                            ? payload.data.message
                            : (aiSettings.messages && aiSettings.messages.success
                                ? aiSettings.messages.success
                                : __('Connection successful.', 'hr-seo-assistant'));
                        setAiTestResult(successMessage, 'success');
                    } else {
                        const errorMessage = payload && payload.data && payload.data.message
                            ? payload.data.message
                            : (aiSettings.messages && aiSettings.messages.error
                                ? aiSettings.messages.error
                                : __('Unable to reach the AI service. Please check your settings and try again.', 'hr-seo-assistant'));
                        setAiTestResult(errorMessage, 'error');
                    }
                })
                .catch(() => {
                    const errorMessage = aiSettings.messages && aiSettings.messages.error
                        ? aiSettings.messages.error
                        : __('Unable to reach the AI service. Please check your settings and try again.', 'hr-seo-assistant');
                    setAiTestResult(errorMessage, 'error');
                })
                .finally(() => {
                    isPending = false;
                    toggleButtonState(false);
                });
        });
    };

    const init = () => {
        bindMediaButtons();
        bindCopyButtons();
        bindFallbackNormalization();
        bindAiTestButton();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
