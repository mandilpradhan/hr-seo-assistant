(() => {
    const wpGlobal = window.wp || {};
    const __ = wpGlobal.i18n && typeof wpGlobal.i18n.__ === 'function'
        ? wpGlobal.i18n.__
        : (text) => text;

    const selectors = {
        mediaButton: '.hr-sa-media-picker',
        copyButton: '.hr-sa-copy-json',
        localeSelect: '.hr-sa-locale-selector',
    };

    let mediaFrame = null;

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

        document.body.removeChild(temp);

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

    const handleCopy = (button) => {
        const sourceId = button.getAttribute('data-source');
        if (!sourceId) {
            return;
        }

        const source = document.getElementById(sourceId);
        if (!source) {
            return;
        }

        const textToCopy = 'value' in source ? source.value : source.textContent;
        if (!textToCopy) {
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

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(textToCopy).then(onSuccess).catch(() => {
                fallbackCopy(textToCopy, onSuccess, onFailure);
            });
            return;
        }

        fallbackCopy(textToCopy, onSuccess, onFailure);
    };

    const openMediaFrame = (targetId) => {
        if (!wpGlobal.media || typeof wpGlobal.media !== 'function') {
            window.alert(__('The media library is unavailable. Please reload the page and try again.', 'hr-seo-assistant'));
            return;
        }

        if (!mediaFrame) {
            mediaFrame = wpGlobal.media({
                title: __('Select fallback image', 'hr-seo-assistant'),
                library: { type: 'image' },
                button: { text: __('Use image', 'hr-seo-assistant') },
                multiple: false,
            });
        }

        mediaFrame.off('select');

        mediaFrame.on('select', () => {
            const inputField = document.getElementById(targetId);
            if (!inputField) {
                return;
            }

            const selection = mediaFrame.state().get('selection');
            const attachment = selection.first();
            if (!attachment) {
                return;
            }

            const url = attachment.get('url');
            inputField.value = url || '';
            inputField.dispatchEvent(new Event('change', { bubbles: true }));
        });

        mediaFrame.open();
    };

    const initLocaleControl = () => {
        const select = document.querySelector(selectors.localeSelect);
        if (!select) {
            return;
        }

        const hiddenInputId = select.getAttribute('data-hidden-input');
        const customInputId = select.getAttribute('data-custom-input');
        const customValue = select.getAttribute('data-custom-value') || '__hr_sa_custom__';

        if (!hiddenInputId || !customInputId) {
            return;
        }

        const hiddenInput = document.getElementById(hiddenInputId);
        const customInput = document.getElementById(customInputId);
        if (!hiddenInput || !customInput) {
            return;
        }

        const optionValues = Array.from(select.options).map((option) => option.value);

        const syncSelect = () => {
            const currentValue = hiddenInput.value;
            if (optionValues.includes(currentValue)) {
                select.value = currentValue;
                customInput.classList.add('hr-sa-hidden');
            } else {
                select.value = customValue;
                customInput.classList.remove('hr-sa-hidden');
                customInput.value = currentValue;
            }
        };

        syncSelect();

        select.addEventListener('change', () => {
            const selectedValue = select.value;
            if (selectedValue === customValue) {
                customInput.classList.remove('hr-sa-hidden');
                customInput.focus();
                hiddenInput.value = customInput.value.trim();
                return;
            }

            customInput.classList.add('hr-sa-hidden');
            hiddenInput.value = selectedValue;
        });

        customInput.addEventListener('input', () => {
            hiddenInput.value = customInput.value.trim();
        });
    };

    const handleDocumentClick = (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const mediaButton = target.closest(selectors.mediaButton);
        if (mediaButton) {
            event.preventDefault();
            const targetId = mediaButton.getAttribute('data-target');
            if (targetId) {
                openMediaFrame(targetId);
            }
            return;
        }

        const copyButton = target.closest(selectors.copyButton);
        if (copyButton) {
            event.preventDefault();
            handleCopy(copyButton);
        }
    };

    const init = () => {
        document.addEventListener('click', handleDocumentClick);
        initLocaleControl();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
