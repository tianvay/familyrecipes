/**
 * Einkaufsliste: Abhaken und Teilen.
 *
 * Die Daten kommen vom Server (ShoppingList::export()). Beim Teilen werden abgehakte
 * Zutaten weggelassen – so kann man zu Hause abhaken, was schon da ist.
 *
 * Teilen-Dialog (navigator.share) und Zwischenablage (navigator.clipboard) gibt es nur in
 * sicheren Kontexten (HTTPS oder localhost). Im Heimnetz per http:// fallen wir daher auf
 * ein eigenes Menü zurück: Kopieren, WhatsApp, E-Mail und Textdatei.
 */
export default (data) => ({
    done: [],
    menuOpen: false,
    copied: false,

    get total() {
        return data.sections.reduce((sum, section) => sum + section.items.length, 0);
    },

    get remaining() {
        return this.total - this.done.length;
    },

    get canUseShareSheet() {
        return typeof navigator.share === 'function';
    },

    text() {
        const lines = [data.title, data.period, ''];

        for (const section of data.sections) {
            const open = section.items.filter((item) => !this.done.includes(item.id));

            if (open.length === 0) {
                continue;
            }

            if (section.heading) {
                lines.push(`${section.heading}:`);
            }

            open.forEach((item) => lines.push(`☐ ${item.line}`));
            lines.push('');
        }

        if (this.remaining === 0) {
            lines.push('Alles abgehakt – nichts mehr zu kaufen.', '');
        }

        lines.push(data.meals);

        return lines.join('\n').trim();
    },

    async share() {
        if (!this.canUseShareSheet) {
            this.menuOpen = !this.menuOpen;

            return;
        }

        try {
            await navigator.share({ title: data.title, text: this.text() });
        } catch (error) {
            // Abbrechen ist kein Fehler; alles andere: eigenes Menü anbieten
            if (error.name !== 'AbortError') {
                this.menuOpen = true;
            }
        }
    },

    /**
     * Kopieren in drei Stufen: Clipboard-API (nur HTTPS) → klassischer Kopierbefehl
     * (geht auch über http://) → Text auf der Seite markiert anzeigen zum Selbstkopieren.
     */
    async copy() {
        const text = this.text();
        this.manualCopy = false;

        try {
            await copyWithClipboardApi(text);
        } catch {
            try {
                copyWithTextarea(text);
            } catch {
                this.showManualCopy();

                return;
            }
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 2500);
    },

    manualCopy: false,

    showManualCopy() {
        this.menuOpen = false;
        this.manualCopy = true;
        this.$nextTick(() => {
            const textarea = this.$refs.manualCopy;
            textarea.value = this.text();
            textarea.focus();
            textarea.select();
        });
    },

    get whatsAppUrl() {
        return `https://wa.me/?text=${encodeURIComponent(this.text())}`;
    },

    get mailUrl() {
        return `mailto:?subject=${encodeURIComponent(data.title)}&body=${encodeURIComponent(this.text())}`;
    },

    download() {
        // BOM, damit Umlaute auch in einfachen Editoren richtig angezeigt werden
        const blob = new Blob(['﻿' + this.text()], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = Object.assign(document.createElement('a'), { href: url, download: data.filename });

        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
});

async function copyWithClipboardApi(text) {
    if (!window.isSecureContext || !navigator.clipboard) {
        throw new Error('Clipboard-API nicht verfügbar');
    }

    await navigator.clipboard.writeText(text);
}

/**
 * Kopieren ohne Clipboard-API (funktioniert auch über http:// im Heimnetz).
 */
function copyWithTextarea(text) {
    const textarea = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';

    document.body.append(textarea);
    textarea.select();
    textarea.setSelectionRange(0, text.length); // iOS

    const ok = document.execCommand('copy');
    textarea.remove();

    if (!ok) {
        throw new Error('Kopieren nicht möglich');
    }
}
