(() => {
    const selectors = {
        mediaButton: '.hr-sa-media-picker',
        copyButton: '.hr-sa-copy-json',
    };

    let mediaFrame = null;

    const openMediaFrame = (targetId) => {
        if (mediaFrame) {
            mediaFrame.off('select');
        }

        mediaFrame = wp.media({
            title: wp.i18n.__('Select fallback image', 'hr-seo-assistant'),
            library: { type: 'image' },
            button: { text: wp.i18n.__('Use image', 'hr-seo-assistant') },
            multiple: false,
        });

        mediaFrame.on('select', () => {
            const selection = mediaFrame.state().get('selection');
            const attachment = selection.first();
            if (!attachment) {
                return;
            }

            const input = document.getElementById(targetId);
            if (input) {
                const url = attachment.get('url');
                input.value = url || '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        mediaFrame.open();
    };

    const handleCopy = (button) => {
        const json = button.getAttribute('data-json') || '';
        if (!json) {
            return;
        }

        navigator.clipboard.writeText(json).then(() => {
            button.setAttribute('data-state', 'copied');
            button.textContent = wp.i18n.__('Copied!', 'hr-seo-assistant');
            setTimeout(() => {
                button.removeAttribute('data-state');
                button.textContent = wp.i18n.__('Copy Context & Settings JSON', 'hr-seo-assistant');
            }, 3000);
        }).catch(() => {
            alert(wp.i18n.__('Unable to copy. Please copy manually.', 'hr-seo-assistant'));
        });
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches(selectors.mediaButton)) {
            event.preventDefault();
            const targetId = target.getAttribute('data-target');
            if (targetId) {
                openMediaFrame(targetId);
            }
            return;
        }

        if (target.matches(selectors.copyButton)) {
            event.preventDefault();
            handleCopy(target);
        }
    });
})();
