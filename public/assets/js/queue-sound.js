/* Browser-gesture-unlocked synthetic chime and queued speech, no media requests. */
(() => {
    'use strict';
    let context = null;
    let speechQueue = [];
    let speaking = false;
    const unlock = () => {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return Promise.resolve(false);
        if (!context) context = new AudioContextClass();
        return context.resume().then(() => true).catch(() => false);
    };
    const play = () => {
        if (!context || context.state !== 'running') return;
        const start = context.currentTime + 0.02;
        [[880, 0], [660, 0.16], [880, 0.44], [660, 0.60]].forEach(([frequency, offset]) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start + offset);
            gain.gain.exponentialRampToValueAtTime(0.09, start + offset + 0.025);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.15);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(start + offset);
            oscillator.stop(start + offset + 0.16);
        });
    };
    const drain = () => {
        if (speaking || speechQueue.length === 0 || !('speechSynthesis' in window)) return;
        speaking = true;
        const utterance = new SpeechSynthesisUtterance(speechQueue.shift());
        const voices = window.speechSynthesis.getVoices();
        const voice = voices.find((v) => v.lang.toLowerCase() === 'en-ph') || voices.find((v) => v.lang.toLowerCase().startsWith('en'));
        if (voice) utterance.voice = voice;
        utterance.lang = voice?.lang || 'en-PH';
        utterance.rate = 0.88;
        utterance.onend = utterance.onerror = () => { speaking = false; drain(); };
        window.speechSynthesis.speak(utterance);
    };
    const announce = (text) => {
        if (!('speechSynthesis' in window)) return;
        speechQueue.push(String(text));
        drain();
    };
    window.CafeteriaChime = Object.freeze({ unlock, play, announce });
})();
