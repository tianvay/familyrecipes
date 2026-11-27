/**
 * In die Zwischenablage kopieren – auch über http:// im Heimnetz.
 *
 * Stufe 1: Clipboard-API (nur HTTPS/localhost), Stufe 2: klassischer Kopierbefehl.
 * Klappt beides nicht, wirft die Funktion; dann den Text zum Selbstkopieren anzeigen.
 */
export async function copyText(text) {
    try {
        await copyWithClipboardApi(text);
    } catch {
        copyWithTextarea(text);
    }
}

async function copyWithClipboardApi(text) {
    if (!window.isSecureContext || !navigator.clipboard) {
        throw new Error('Clipboard-API nicht verfügbar');
    }

    await navigator.clipboard.writeText(text);
}

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
