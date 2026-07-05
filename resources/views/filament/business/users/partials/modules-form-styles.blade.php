<style>
    .user-modules-intro {
        margin-bottom: 1rem;
    }

    .user-modules-summary {
        margin-bottom: 1rem;
        border-color: rgb(226 232 240);
        background: rgb(248 250 252);
        color: rgb(51 65 85);
    }

    .dark .user-modules-summary {
        border-color: rgb(255 255 255 / 0.08);
        background: rgb(255 255 255 / 0.03);
        color: rgb(203 213 225);
    }

    .user-modules-summary--empty {
        border-color: rgb(254 202 202);
        background: rgb(254 242 242);
        color: rgb(127 29 29);
    }

    .dark .user-modules-summary--empty {
        border-color: rgb(248 113 113 / 0.25);
        background: rgb(127 29 29 / 0.15);
        color: rgb(254 202 202);
    }

    .user-modules-summary--active {
        border-color: rgb(186 230 253);
        background: rgb(240 249 255);
        color: rgb(12 74 110);
    }

    .dark .user-modules-summary--active {
        border-color: rgb(56 189 248 / 0.25);
        background: rgb(8 47 73 / 0.35);
        color: rgb(186 230 253);
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-actions {
        margin-bottom: 0.75rem;
        padding-bottom: 0.65rem;
        border-bottom: 1px dashed rgb(203 213 225);
    }

    .dark .user-modules-checkbox-list .fi-fo-checkbox-list-actions {
        border-bottom-color: rgb(255 255 255 / 0.1);
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-options {
        gap: 0.75rem !important;
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-option {
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.9rem 1rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(226 232 240);
        background: rgb(255 255 255);
        transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    }

    .dark .user-modules-checkbox-list .fi-fo-checkbox-list-option {
        border-color: rgb(255 255 255 / 0.08);
        background: rgb(255 255 255 / 0.03);
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-option:hover {
        border-color: rgb(125 211 252);
        box-shadow: 0 8px 24px -16px rgb(14 165 233 / 0.45);
    }

    .dark .user-modules-checkbox-list .fi-fo-checkbox-list-option:hover {
        border-color: rgb(56 189 248 / 0.35);
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) {
        border-color: rgb(14 165 233);
        background: rgb(240 249 255);
    }

    .dark .user-modules-checkbox-list .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) {
        border-color: rgb(56 189 248 / 0.45);
        background: rgb(8 47 73 / 0.45);
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-option-label {
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.45;
    }

    .user-modules-checkbox-list .fi-fo-checkbox-list-search-input-wrp {
        margin-bottom: 0.75rem;
    }

    .user-modules-hint {
        margin-top: 1rem;
    }
</style>
