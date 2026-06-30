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

if (window.Alpine) {
    registerGuiaChatUiStore();
} else {
    document.addEventListener('alpine:init', registerGuiaChatUiStore);
}
