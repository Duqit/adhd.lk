document.addEventListener('DOMContentLoaded', () => {
    let audioCtx;
    let brownNoiseNode;
    let rainNode;
    let oceanNode;

    const volBrown = document.getElementById('vol-brown');
    const volRain = document.getElementById('vol-rain');
    const volOcean = document.getElementById('vol-ocean');
    const stopAllBtn = document.getElementById('stop-all-sounds');

    function initAudio() {
        if (audioCtx) return; // already initialized
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();

        // --- Brown Noise Generator ---
        const bufferSize = audioCtx.sampleRate * 2; // 2 seconds
        const noiseBuffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
        const output = noiseBuffer.getChannelData(0);
        let lastOut = 0;
        for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            output[i] = (lastOut + (0.02 * white)) / 1.02;
            lastOut = output[i];
            output[i] *= 3.5; // (rough) compensate for gain
        }

        // Brown Noise Setup
        const brownSource = audioCtx.createBufferSource();
        brownSource.buffer = noiseBuffer;
        brownSource.loop = true;
        const brownGain = audioCtx.createGain();
        brownGain.gain.value = 0;
        brownSource.connect(brownGain);
        brownGain.connect(audioCtx.destination);
        brownSource.start(0);
        brownNoiseNode = brownGain;

        // --- Rain Setup (Filtered Brown Noise) ---
        const rainSource = audioCtx.createBufferSource();
        rainSource.buffer = noiseBuffer;
        rainSource.loop = true;
        const rainFilter = audioCtx.createBiquadFilter();
        rainFilter.type = 'lowpass';
        rainFilter.frequency.value = 800; // Muffled rain
        const rainGain = audioCtx.createGain();
        rainGain.gain.value = 0;
        rainSource.connect(rainFilter);
        rainFilter.connect(rainGain);
        rainGain.connect(audioCtx.destination);
        rainSource.start(0);
        rainNode = rainGain;

        // --- Ocean Waves Setup (Modulated Noise) ---
        const oceanSource = audioCtx.createBufferSource();
        oceanSource.buffer = noiseBuffer;
        oceanSource.loop = true;
        const oceanFilter = audioCtx.createBiquadFilter();
        oceanFilter.type = 'lowpass';
        oceanFilter.frequency.value = 400;
        
        // Modulated Gain (Base level 0.5, modulated +/- 0.5)
        const oceanModGain = audioCtx.createGain();
        oceanModGain.gain.value = 0.5; 
        
        const modulator = audioCtx.createOscillator();
        modulator.type = 'sine';
        modulator.frequency.value = 0.1; // 10 seconds per wave
        const modulatorGain = audioCtx.createGain();
        modulatorGain.gain.value = 0.5; // Depth of modulation
        modulator.connect(modulatorGain);
        modulatorGain.connect(oceanModGain.gain);
        modulator.start(0);

        // Master Gain for Ocean (Slider controls this)
        const oceanMasterGain = audioCtx.createGain();
        oceanMasterGain.gain.value = 0;

        oceanSource.connect(oceanFilter);
        oceanFilter.connect(oceanModGain);
        oceanModGain.connect(oceanMasterGain);
        oceanMasterGain.connect(audioCtx.destination);
        oceanSource.start(0);
        
        oceanNode = oceanMasterGain;
    }

    function setVolume(node, slider) {
        if (!audioCtx) {
            initAudio();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        if (node) {
            const targetVol = parseFloat(slider.value);
            node.gain.cancelScheduledValues(audioCtx.currentTime);
            node.gain.setValueAtTime(node.gain.value, audioCtx.currentTime);
            node.gain.linearRampToValueAtTime(targetVol, audioCtx.currentTime + 0.1);
        }
    }

    // --- Volume persistence: restore slider positions, save on change ---
    const AMBIENT_KEY = 'adhd_ambient_vols';
    function saveVols() {
        try {
            localStorage.setItem(AMBIENT_KEY, JSON.stringify({
                brown: volBrown.value, rain: volRain.value, ocean: volOcean.value
            }));
        } catch (e) {}
    }
    try {
        const saved = JSON.parse(localStorage.getItem(AMBIENT_KEY) || 'null');
        if (saved) {
            volBrown.value = saved.brown || 0;
            volRain.value = saved.rain || 0;
            volOcean.value = saved.ocean || 0;
        }
    } catch (e) {}

    // Autoplay policies require a user gesture — resume saved sounds on the
    // first interaction anywhere on the page.
    function resumeSavedOnGesture() {
        if (parseFloat(volBrown.value) > 0 || parseFloat(volRain.value) > 0 || parseFloat(volOcean.value) > 0) {
            setVolume(null, volBrown); // initializes audio ctx
            setVolume(brownNoiseNode, volBrown);
            setVolume(rainNode, volRain);
            setVolume(oceanNode, volOcean);
        }
        document.removeEventListener('pointerdown', resumeSavedOnGesture);
    }
    document.addEventListener('pointerdown', resumeSavedOnGesture, { once: true });

    volBrown.addEventListener('input', () => { setVolume(brownNoiseNode, volBrown); saveVols(); });
    volRain.addEventListener('input', () => { setVolume(rainNode, volRain); saveVols(); });
    volOcean.addEventListener('input', () => { setVolume(oceanNode, volOcean); saveVols(); });

    stopAllBtn.addEventListener('click', () => {
        volBrown.value = 0;
        volRain.value = 0;
        volOcean.value = 0;
        saveVols();
        if (audioCtx) {
            const t = audioCtx.currentTime;
            
            brownNoiseNode.gain.cancelScheduledValues(t);
            brownNoiseNode.gain.setValueAtTime(brownNoiseNode.gain.value, t);
            brownNoiseNode.gain.linearRampToValueAtTime(0, t + 0.1);
            
            rainNode.gain.cancelScheduledValues(t);
            rainNode.gain.setValueAtTime(rainNode.gain.value, t);
            rainNode.gain.linearRampToValueAtTime(0, t + 0.1);
            
            oceanNode.gain.cancelScheduledValues(t);
            oceanNode.gain.setValueAtTime(oceanNode.gain.value, t);
            oceanNode.gain.linearRampToValueAtTime(0, t + 0.1);
        }
    });
});
