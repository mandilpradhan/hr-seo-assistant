(() => {
    const wpGlobal = window.wp || {};
    const __ = wpGlobal.i18n && typeof wpGlobal.i18n.__ === 'function'
        ? wpGlobal.i18n.__
        : (text) => text;

    const selectors = {
        mediaButton: '.hr-sa-media-picker',
        copyButton: '.hr-sa-copy-json',
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

        mediaFrame = wpGlobal.media({
            title: __('Select fallback image', 'hr-seo-assistant'),
            library: { type: 'image' },
            button: { text: __('Use image', 'hr-seo-assistant') },
            multiple: false,
        });

        mediaFrame.on('select', () => {
            const inputField = document.getElementById(targetId);
            if (!inputField) {
                return;
            }

            const state = mediaFrame.state();
            const selection = state && typeof state.get === 'function' ? state.get('selection') : null;
            const attachment = selection && typeof selection.first === 'function' ? selection.first() : null;
            if (!attachment) {
                return;
            }

            const url = attachment.get('url');
            if (typeof url === 'string') {
                inputField.value = url;
                inputField.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        mediaFrame.open();
    };

    const bindMediaButtons = () => {
        const buttons = document.querySelectorAll(selectors.mediaButton);
        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = button.getAttribute('data-target');
                if (targetId) {
                    openMediaFrame(targetId);
                }
            });
        });
    };

    const bindCopyButtons = () => {
        const buttons = document.querySelectorAll(selectors.copyButton);
        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                handleCopy(button);
            });
        });
    };

    const init = () => {
        bindMediaButtons();
        bindCopyButtons();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
