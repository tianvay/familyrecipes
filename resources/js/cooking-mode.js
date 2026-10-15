/**
 * Kochmodus: Schritte blättern (Knöpfe, Wischen, Pfeiltasten), Zutatenliste einblenden,
 * Timer für Zeitangaben wie „12–15 Minuten“ mit Signalton.
 */
export default ({ steps }) => ({
    steps,
    index: 0,
    finished: false,
    showIngredients: false,
    checked: [],

    timer: null, // { label, total, remaining, step, done }
    timerHandle: null,
    touchStartX: null,

    init() {
        this.keepScreenOn();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.keepScreenOn();
            }
        });
    },

    get step() {
        return this.steps[this.index];
    },

    get isFirst() {
        return this.index === 0;
    },

    get isLast() {
        return this.index === this.steps.length - 1;
    },

    next() {
        if (this.finished) {
            return;
        }

        if (this.isLast) {
            this.finished = true;

            return;
        }

        this.index++;
    },

    previous() {
        if (this.finished) {
            this.finished = false;

            return;
        }

        if (!this.isFirst) {
            this.index--;
        }
    },

    goTo(index) {
        this.finished = false;
        this.index = index;
    },

    onKey(event) {
        if (event.key === 'ArrowRight' || event.key === ' ') {
            event.preventDefault();
            this.next();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.previous();
        } else if (event.key === 'Escape') {
            this.showIngredients = false;
        }
    },

    onTouchStart(event) {
        this.touchStartX = event.changedTouches[0].clientX;
    },

    onTouchEnd(event) {
        if (this.touchStartX === null) {
            return;
        }

        const distance = event.changedTouches[0].clientX - this.touchStartX;
        this.touchStartX = null;

        if (Math.abs(distance) < 60) {
            return;
        }

        distance < 0 ? this.next() : this.previous();
    },

    // --- Timer ---------------------------------------------------------------

    startTimer(step) {
        this.stopTimer();
        unlockAudio();

        this.timer = { label: step.timer.label, total: step.timer.seconds, remaining: step.timer.seconds, step: step.number, done: false };
        const endsAt = Date.now() + step.timer.seconds * 1000;

        // Restzeit aus der Uhrzeit berechnen – bleibt korrekt, auch wenn das iPad den Tab pausiert
        this.timerHandle = setInterval(() => {
            this.timer.remaining = Math.max(0, Math.round((endsAt - Date.now()) / 1000));

            if (this.timer.remaining === 0) {
                clearInterval(this.timerHandle);
                this.timer.done = true;
                ring();
            }
        }, 250);
    },

    stopTimer() {
        clearInterval(this.timerHandle);
        this.timer = null;
    },

    get timerDisplay() {
        if (!this.timer) {
            return '';
        }

        const minutes = Math.floor(this.timer.remaining / 60);
        const seconds = String(this.timer.remaining % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },

    // --- Bildschirm wach halten (nur über HTTPS/localhost verfügbar, sonst ohne Wirkung) ---

    async keepScreenOn() {
        try {
            await navigator.wakeLock?.request('screen');
        } catch {
            // nicht unterstützt oder abgelehnt – kein Problem
        }
    },
});

let audioContext = null;

/**
 * iOS spielt Töne nur ab, wenn das Audio durch eine Berührung freigeschaltet wurde.
 */
function unlockAudio() {
    const AudioContext = window.AudioContext || window.webkitAudioContext;

    if (!AudioContext) {
        return;
    }

    audioContext ??= new AudioContext();
    audioContext.resume?.();
}

/**
 * Drei kurze Töne als Timer-Signal.
 */
function ring() {
    if (!audioContext) {
        return;
    }

    for (let i = 0; i < 3; i++) {
        const start = audioContext.currentTime + i * 0.6;
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.4, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.45);

        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.5);
    }
}
