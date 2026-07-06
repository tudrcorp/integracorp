function registerGuiaChatUiStore() {
    if (typeof window.guiaChatUiStore === 'function') {
        window.guiaChatUiStore();

        return;
    }

    window.Alpine.store('guiaChatUi', {
        optimisticUserMessage: null,
        optimisticThinking: false,
        awaitingReply: false,
        isSending: false,
        isTypingVisible() {
            return this.optimisticThinking || this.awaitingReply;
        },
        beginAwaitingReply() {
            this.awaitingReply = true;
            this.isSending = true;
            window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
        },
        beginSend(message = null) {
            this.optimisticUserMessage = message;
            this.optimisticThinking = true;
            this.awaitingReply = true;
            this.isSending = true;
            window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
        },
        endSend() {
            this.optimisticUserMessage = null;
            this.optimisticThinking = false;
            this.isSending = false;
        },
        clearAwaitingReply() {
            this.awaitingReply = false;
        },
    });
}

function guiaChatShouldLockMobileViewport() {
    return window.matchMedia('(max-width: 639px), (pointer: coarse)').matches;
}

function setupGuiaChatMobileViewportLock() {
    const root = document.getElementById('guia-chat-root');

    if (! root || ! window.visualViewport || ! guiaChatShouldLockMobileViewport()) {
        return;
    }

    const backgrounds = () => document.querySelectorAll('.guia-chat-bg');
    const overlays = () => document.querySelectorAll('[data-guia-chat-overlay]');
    let rafId = null;

    const clearInlineViewportStyles = () => {
        root.style.removeProperty('top');
        root.style.removeProperty('left');
        root.style.removeProperty('right');
        root.style.removeProperty('bottom');
        root.style.removeProperty('width');
        root.style.removeProperty('height');
        root.style.removeProperty('max-width');
        root.style.removeProperty('transform');

        backgrounds().forEach((background) => {
            background.style.removeProperty('width');
            background.style.removeProperty('height');
        });

        overlays().forEach((overlay) => {
            overlay.style.removeProperty('top');
            overlay.style.removeProperty('left');
            overlay.style.removeProperty('right');
            overlay.style.removeProperty('bottom');
            overlay.style.removeProperty('width');
            overlay.style.removeProperty('height');
            overlay.style.removeProperty('max-width');
        });
    };

    const syncOverlays = (width, height, offsetTop, offsetLeft) => {
        overlays().forEach((overlay) => {
            overlay.style.top = `${offsetTop}px`;
            overlay.style.left = `${offsetLeft}px`;
            overlay.style.right = 'auto';
            overlay.style.bottom = 'auto';
            overlay.style.width = `${width}px`;
            overlay.style.height = `${height}px`;
            overlay.style.maxWidth = `${width}px`;
        });
    };

    const syncViewport = () => {
        if (! guiaChatShouldLockMobileViewport()) {
            document.documentElement.classList.remove('guia-chat-keyboard-open');
            clearInlineViewportStyles();

            return;
        }

        const viewport = window.visualViewport;
        const width = Math.round(viewport.width);
        const height = Math.round(viewport.height);
        const offsetTop = Math.round(viewport.offsetTop);
        const offsetLeft = Math.round(viewport.offsetLeft);
        const keyboardOpen = height < window.innerHeight - 80;

        document.documentElement.classList.toggle('guia-chat-keyboard-open', keyboardOpen);

        root.style.top = `${offsetTop}px`;
        root.style.left = `${offsetLeft}px`;
        root.style.right = 'auto';
        root.style.bottom = 'auto';
        root.style.width = `${width}px`;
        root.style.height = `${height}px`;
        root.style.maxWidth = `${width}px`;
        root.style.transform = 'translateZ(0)';

        backgrounds().forEach((background) => {
            background.style.width = `${width}px`;
            background.style.height = `${height}px`;
        });

        syncOverlays(width, height, offsetTop, offsetLeft);
    };

    const scheduleSyncViewport = () => {
        if (rafId !== null) {
            cancelAnimationFrame(rafId);
        }

        rafId = requestAnimationFrame(() => {
            rafId = null;
            syncViewport();
        });
    };

    window.visualViewport.addEventListener('resize', scheduleSyncViewport);
    window.visualViewport.addEventListener('scroll', scheduleSyncViewport);
    window.addEventListener('orientationchange', scheduleSyncViewport);
    window.addEventListener('resize', scheduleSyncViewport);

    document.addEventListener('focusin', (event) => {
        if (event.target instanceof HTMLElement && event.target.closest('#guia-chat-root')) {
            scheduleSyncViewport();
        }
    });

    document.addEventListener('focusout', (event) => {
        if (event.target instanceof HTMLElement && event.target.closest('#guia-chat-root')) {
            window.setTimeout(scheduleSyncViewport, 120);
        }
    });

    window.addEventListener('guia-chat-menu-opened', scheduleSyncViewport);

    scheduleSyncViewport();
}

function initGuiaChatUi() {
    registerGuiaChatUiStore();
    setupGuiaChatMobileViewportLock();
}

if (window.Alpine) {
    initGuiaChatUi();
} else {
    document.addEventListener('alpine:init', registerGuiaChatUiStore);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupGuiaChatMobileViewportLock);
} else {
    setupGuiaChatMobileViewportLock();
}
