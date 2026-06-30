function registerChatTypewriter() {
    window.Alpine.data('chatTypewriter', (fullText, formattedHtml) => ({
        fullText: String(fullText ?? ''),
        formattedHtml: String(formattedHtml ?? ''),
        displayed: '',
        finished: false,
        isTyping: false,

        get displayedPlain() {
            return this.displayed.replace(/\*\*/g, '');
        },

        init() {
            const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

            if (reduceMotion || this.fullText === '') {
                this.finished = true;

                return;
            }

            this.$nextTick(() => this.type());
        },

        async type() {
            this.isTyping = true;

            const text = this.fullText;
            const lengthScale = text.length > 600 ? 0.58 : text.length > 350 ? 0.72 : text.length > 180 ? 0.85 : 1;
            const speedFactor = 0.52;

            for (let index = 0; index < text.length; index++) {
                const character = text[index];
                const nextCharacter = text[index + 1] ?? '';

                this.displayed += character;

                if (index % 6 === 0) {
                    window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
                }

                await this.sleep(this.delayFor(character, nextCharacter) * lengthScale * speedFactor);
            }

            this.isTyping = false;
            this.finished = true;
            window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
        },

        delayFor(character, nextCharacter) {
            const randomBetween = (min, max) => Math.floor(min + Math.random() * (max - min + 1));

            if (character === '\n') {
                return randomBetween(55, 95);
            }

            if (/[.!?¿¡]/.test(character)) {
                return randomBetween(150, 240);
            }

            if (/[,;:]/.test(character)) {
                return randomBetween(45, 75);
            }

            if (character === ' ') {
                return randomBetween(12, 26);
            }

            if (character === '-' || character === '/') {
                return randomBetween(20, 36);
            }

            if (/[0-9]/.test(character) && /[0-9]/.test(nextCharacter)) {
                return randomBetween(10, 22);
            }

            return randomBetween(8, 22);
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
