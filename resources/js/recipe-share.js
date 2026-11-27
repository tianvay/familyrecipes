import { copyText } from './clipboard';

/**
 * Rezept teilen als einheitlicher Text (RecipeShareText), den jeder lesen und
 * jedes Rezeptbuch unter „Rezept importieren“ wieder einlesen kann. Ohne HTTPS gibt es weder Teilen-Dialog
 * noch Clipboard-API – dann helfen Kopierbefehl, WhatsApp-/Mail-Link und das
 * markierbare Textfeld.
 */
export default (data) => ({
    open: false,
    copied: false,
    copyFailed: false,

    get canUseShareSheet() {
        return typeof navigator.share === 'function';
    },

    async shareSheet() {
        try {
            await navigator.share({ title: data.title, text: data.text });
        } catch {
            // Abgebrochen oder nicht erlaubt – die anderen Wege stehen ja daneben.
        }
    },

    async copy() {
        try {
            await copyText(data.text);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2500);
        } catch {
            this.copyFailed = true;
            this.selectAll();
        }
    },

    selectAll() {
        const textarea = this.$refs.shareText;
        textarea.focus();
        textarea.setSelectionRange(0, textarea.value.length);
    },

    get whatsAppUrl() {
        return `https://wa.me/?text=${encodeURIComponent(data.text)}`;
    },

    get mailUrl() {
        return `mailto:?subject=${encodeURIComponent(`Rezept: ${data.title}`)}&body=${encodeURIComponent(data.text)}`;
    },
});
