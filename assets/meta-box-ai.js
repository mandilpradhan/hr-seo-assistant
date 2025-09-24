(() => {
    const settings = window.hrSaAiMetaBox || null;
    if (!settings) {
        return;
    }

    const wpGlobal = window.wp || {};
    const __ = wpGlobal.i18n && typeof wpGlobal.i18n.__ === 'function'
        ? wpGlobal.i18n.__
        : (text) => text;

    const selectors = {
        generateButton: '.hr-sa-ai-generate',
    };

    const fieldMap = settings.fields || {};
    const actionMap = {
        title: 'hr_sa_generate_title',
        description: 'hr_sa_generate_description',
        keywords: 'hr_sa_generate_keywords',
    };

    let isPending = false;

    const findField = (fieldId) => {
        if (!fieldId) {
            return null;
        }
        return document.getElementById(fieldId);
    };

    const updateFieldValue = (field, value) => {
        if (!field || typeof field.value === 'undefined') {
            return;
        }

        const newValue = typeof value === 'string' ? value : '';
        if (field.value === newValue) {
            return;
        }

        field.value = newValue;
        try {
            field.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) {
            const changeEvent = document.createEvent('HTMLEvents');
            changeEvent.initEvent('change', true, false);
            field.dispatchEvent(changeEvent);
        }
    };

    const setButtonState = (button, state) => {
        if (!button) {
            return;
        }

        if (state === 'loading') {
            button.classList.add('hr-sa-btn-loading');
            button.setAttribute('disabled', 'disabled');
        } else {
            button.classList.remove('hr-sa-btn-loading');
            button.removeAttribute('disabled');
        }
    };

    const showError = (message) => {
        const text = typeof message === 'string' && message !== ''
            ? message
            : __('We could not generate content. Please try again later.', 'hr-seo-assistant');
        window.alert(text);
    };

    const handleGeneration = (button, fieldKey) => {
        if (!button || !fieldKey) {
            return;
        }

        if (typeof window.fetch !== 'function') {
            showError(__('Your browser does not support the fetch API.', 'hr-seo-assistant'));
            return;
        }

        if (!settings.aiEnabled) {
            showError(settings.messages && settings.messages.disabled
                ? settings.messages.disabled
                : __('AI assistance is disabled. Enable it in the settings page to generate suggestions.', 'hr-seo-assistant'));
            return;
        }

        if (!settings.postId) {
            showError(settings.messages && settings.messages.missingPost
                ? settings.messages.missingPost
                : __('Save the post before requesting AI suggestions.', 'hr-seo-assistant'));
            return;
        }

        const action = actionMap[fieldKey] || '';
        const fieldId = fieldMap[fieldKey] || '';
        const targetField = findField(fieldId);

        if (!action || !targetField) {
            showError(__('We could not locate the target field for this action.', 'hr-seo-assistant'));
            return;
        }

        if (isPending) {
            return;
        }

        isPending = true;
        setButtonState(button, 'loading');

        const params = new window.URLSearchParams();
        params.append('action', action);
        params.append('nonce', settings.nonce);
        params.append('post_id', String(settings.postId));

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        })
            .then((response) => response.json())
            .then((payload) => {
                if (payload && payload.success && payload.data && typeof payload.data.value === 'string') {
                    updateFieldValue(targetField, payload.data.value);
                } else {
                    const message = payload && payload.data && payload.data.message
                        ? payload.data.message
                        : (settings.messages && settings.messages.requestError
                            ? settings.messages.requestError
                            : __('We could not generate content. Please try again later.', 'hr-seo-assistant'));
                    showError(message);
                }
            })
            .catch(() => {
                const message = settings.messages && settings.messages.requestError
                    ? settings.messages.requestError
                    : __('We could not generate content. Please try again later.', 'hr-seo-assistant');
                showError(message);
            })
            .finally(() => {
                isPending = false;
                setButtonState(button, 'idle');
            });
    };

    const bindGenerateButtons = () => {
        const buttons = document.querySelectorAll(selectors.generateButton);
        if (!buttons || buttons.length === 0) {
            return;
        }

        for (let index = 0; index < buttons.length; index += 1) {
            const button = buttons[index];
            if (!button) {
                continue;
            }

            const fieldKey = button.getAttribute('data-hr-sa-ai-action');

            button.addEventListener('click', (event) => {
                event.preventDefault();
                handleGeneration(button, fieldKey);
            });
        }
    };

    const init = () => {
        bindGenerateButtons();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
