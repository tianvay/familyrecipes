/**
 * Rezeptformular, Bereich „Aussehen“: Emoji/Farbe-Vorschau und Foto-Upload.
 *
 * Fotos vom iPhone sind oft mehrere MB groß. Vor dem Hochladen rechnen wir sie im Browser
 * auf höchstens MAX_SIZE Pixel (lange Seite) herunter und speichern sie als JPEG.
 * Klappt das nicht (ältere Geräte), wird das Original hochgeladen.
 */
const MAX_SIZE = 1600;
const QUALITY = 0.85;
const KEEP_BELOW_BYTES = 800 * 1024; // kleine Bilder unverändert lassen

export default ({ emoji, color, imageUrl }) => ({
    emoji,
    color,
    preview: imageUrl,
    processing: false,
    info: '',

    async onFileChange(event) {
        const input = event.target;
        const file = input.files?.[0];
        this.info = '';

        if (!file) {
            this.preview = imageUrl;

            return;
        }

        this.processing = true;

        try {
            const resized = await resizeImage(file);

            if (resized !== file && replaceInputFile(input, resized)) {
                this.info = `Foto verkleinert: ${formatSize(file.size)} → ${formatSize(resized.size)}`;
            }

            this.preview = URL.createObjectURL(input.files[0]);
        } catch {
            // Original behalten; der Server prüft Größe und Format
            this.preview = URL.createObjectURL(file);
        } finally {
            this.processing = false;
        }
    },
});

async function resizeImage(file) {
    const image = await loadImage(file);
    const scale = Math.min(1, MAX_SIZE / Math.max(image.naturalWidth, image.naturalHeight));

    if (scale === 1 && file.size <= KEEP_BELOW_BYTES) {
        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(image.naturalWidth * scale);
    canvas.height = Math.round(image.naturalHeight * scale);

    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff'; // transparente PNGs bekommen weißen statt schwarzen Hintergrund
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));

    if (!blob || blob.size >= file.size) {
        return file;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Bild konnte nicht gelesen werden'));
        };
        image.src = url;
    });
}

/**
 * Ersetzt die Datei im <input type="file">, damit das normale Formular sie hochlädt.
 */
function replaceInputFile(input, file) {
    try {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;

        return input.files[0] === file || input.files[0]?.size === file.size;
    } catch {
        return false;
    }
}

function formatSize(bytes) {
    return `${(bytes / 1024 / 1024).toLocaleString('de-DE', { maximumFractionDigits: 1, minimumFractionDigits: 1 })} MB`;
}
