const REPLY_PREPARE_DELAY_MS = 750;
const REPLY_PREPARE_JITTER_MS = 350;

function registerChatTypewriter() {
    window.Alpine.data('chatTypewriter', (fullText, formattedHtml, mode = 'welcome') => ({
        fullText: String(fullText ?? ''),
        formattedHtml: String(formattedHtml ?? ''),
        displayed: '',
        finished: false,
        isTyping: false,
        preparing: mode === 'reply',

        get displayedPlain() {
            return this.displayed.replace(/\*\*/g, '');
        },

        init() {
            if (mode === 'reply') {
                this.$nextTick(() => this.prepareAndReply());

                return;
            }

            const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

            if (reduceMotion || this.fullText === '') {
                this.finished = true;

                return;
            }

            this.$nextTick(() => this.type());
        },

        replyPrepareDelay() {
            const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

            if (reduceMotion) {
                return 0;
            }

            return REPLY_PREPARE_DELAY_MS + Math.floor(Math.random() * REPLY_PREPARE_JITTER_MS);
        },

        async prepareAndReply() {
            await this.sleep(this.replyPrepareDelay());
            this.preparing = false;
            this.notifyReplyVisible();
            window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));

            const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

            if (reduceMotion || this.fullText === '') {
                this.finished = true;

                return;
            }

            if (this.fullText.length > 220) {
                this.finished = true;

                return;
            }

            await this.type();
        },

        notifyReplyVisible() {
            if (mode !== 'reply') {
                return;
            }

            window.dispatchEvent(new CustomEvent('guia-chat-reply-visible'));
        },

        async type() {
            this.isTyping = true;

            const text = this.fullText;
            const isReply = mode === 'reply';
            const lengthScale = isReply
                ? (text.length > 400 ? 0.28 : text.length > 200 ? 0.42 : 0.58)
                : (text.length > 600 ? 0.58 : text.length > 350 ? 0.72 : text.length > 180 ? 0.85 : 1);
            const speedFactor = isReply ? 0.16 : 0.52;

            for (let index = 0; index < text.length; index++) {
                const character = text[index];
                const nextCharacter = text[index + 1] ?? '';

                this.displayed += character;

                if (index % 6 === 0) {
                    window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
                }

                await this.sleep(this.delayFor(character, nextCharacter, isReply) * lengthScale * speedFactor);
            }

            this.isTyping = false;
            this.finished = true;
            window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
        },

        delayFor(character, nextCharacter, isReply) {
            const randomBetween = (min, max) => Math.floor(min + Math.random() * (max - min + 1));
            const pace = isReply ? 0.55 : 1;

            if (character === '\n') {
                return randomBetween(55, 95) * pace;
            }

            if (/[.!?¿¡]/.test(character)) {
                return randomBetween(150, 240) * pace;
            }

            if (/[,;:]/.test(character)) {
                return randomBetween(45, 75) * pace;
            }

            if (character === ' ') {
                return randomBetween(12, 26) * pace;
            }

            if (character === '-' || character === '/') {
                return randomBetween(20, 36) * pace;
            }

            if (/[0-9]/.test(character) && /[0-9]/.test(nextCharacter)) {
                return randomBetween(10, 22) * pace;
            }

            return randomBetween(8, 22) * pace;
        },

        sleep(milliseconds) {
            return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
        },
    }));
}

if (window.Alpine) {
    registerChatTypewriter();
} else {
    document.addEventListener('alpine:init', registerChatTypewriter);
}
