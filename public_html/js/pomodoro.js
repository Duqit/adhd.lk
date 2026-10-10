// i18n lookup with English fallback
var TJ = function (k, fb) { return (window.ADHD_I18N && window.ADHD_I18N[k]) || fb; };

document.addEventListener('DOMContentLoaded', () => {
    // Timer configurations (in seconds)
    const MODES = {
        '15': 15 * 60,
        '25': 25 * 60,
        '50': 50 * 60,
        'custom': 30 * 60,
        'shortBreak': 5 * 60,
        'stopwatch': 0 // count up
    };
    const FOCUS_MODES = ['15', '25', '50', 'custom'];

    const STATE_KEY = 'adhd_timer_state';
    const STATS_KEY = 'adhd_focus_stats';
    const CUSTOM_KEY = 'adhd_timer_custom';

    let currentMode = '25';
    let lastFocusMode = '25';   // which focus mode a break should return to
    let isRunning = false;
    let endTime = null;         // ms timestamp when a countdown finishes
    let remaining = MODES[currentMode]; // seconds (authoritative when paused)
    let totalTime = MODES[currentMode];
    let stopwatchStart = null;  // ms timestamp when the stopwatch (re)started
    let stopwatchBase = 0;      // accumulated seconds before current run
    let tickInterval = null;
    let distractions = 0;
    let wakeLock = null;
    let lastShownSecond = -1;

    // DOM Elements
    const displayElement = document.getElementById('timer-display');
    const startBtn = document.getElementById('start-btn');
    const resetBtn = document.getElementById('reset-btn');
    const modeBtns = document.querySelectorAll('.mode-btn[data-mode]');
    const timerProgress = document.getElementById('timer-progress');
    const intentionInput = document.getElementById('focus-intention');
    const logDistractionBtn = document.getElementById('log-distraction-btn');
    const distractionCount = document.getElementById('distraction-count');
    const statsLine = document.getElementById('focus-stats');
    const customRow = document.getElementById('custom-row');
    const customInput = document.getElementById('custom-mins');
    const customSetBtn = document.getElementById('custom-set');
    const customBtn = document.querySelector('.mode-btn[data-mode="custom"]');
    // Fullscreen focus overlay
    const fsBtn = document.getElementById('fs-btn');
    const fsOverlay = document.getElementById('focus-fs');
    const fsTimer = document.getElementById('fs-timer');
    const fsIntention = document.getElementById('fs-intention');
    const fsToggle = document.getElementById('fs-toggle');
    const fsExit = document.getElementById('fs-exit');

    const circleCircumference = 2 * Math.PI * 115;
    timerProgress.style.strokeDasharray = circleCircumference;

    /* ---------------- helpers ---------------- */
    function todayStr() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    }

    function lsGet(key, def) {
        try { const v = localStorage.getItem(key); return v === null ? def : JSON.parse(v); }
        catch (e) { return def; }
    }
    function lsSet(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    function formatTime(seconds) {
        seconds = Math.max(0, seconds);
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    function currentSeconds() {
        if (currentMode === 'stopwatch') {
            return isRunning
                ? stopwatchBase + Math.floor((Date.now() - stopwatchStart) / 1000)
                : stopwatchBase;
        }
        return isRunning ? Math.max(0, Math.round((endTime - Date.now()) / 1000)) : remaining;
    }

    /* ---------------- persistence ---------------- */
    function saveState() {
        lsSet(STATE_KEY, {
            mode: currentMode,
            lastFocusMode: lastFocusMode,
            running: isRunning,
            endTime: endTime,
            remaining: remaining,
            totalTime: totalTime,
            stopwatchStart: stopwatchStart,
            stopwatchBase: stopwatchBase,
            distractions: distractions,
            intention: intentionInput.value
        });
    }

    function restoreState() {
        MODES.custom = Math.max(60, (lsGet(CUSTOM_KEY, 30)) * 60);
        updateCustomLabel();

        const s = lsGet(STATE_KEY, null);
        if (!s || !MODES.hasOwnProperty(s.mode)) return;

        currentMode = s.mode;
        lastFocusMode = s.lastFocusMode || '25';
        totalTime = s.totalTime || MODES[currentMode];
        distractions = s.distractions || 0;
        if (s.intention) intentionInput.value = s.intention;
        distractionCount.textContent = distractions;

        modeBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === currentMode));

        if (currentMode === 'stopwatch') {
            stopwatchBase = s.stopwatchBase || 0;
            if (s.running && s.stopwatchStart) {
                stopwatchStart = s.stopwatchStart;
                beginTicking(true);
            }
            return;
        }

        if (s.running && s.endTime) {
            if (s.endTime > Date.now()) {
                endTime = s.endTime;
                beginTicking(true);
            } else {
                // Finished while the page was closed — treat as completed.
                remaining = 0;
                handleCompletion(true);
            }
        } else {
            remaining = (typeof s.remaining === 'number') ? s.remaining : MODES[currentMode];
            if (remaining > 0 && remaining < totalTime) {
                startBtn.textContent = TJ('js_resume', 'Resume');
            }
        }
    }

    /* ---------------- daily stats ---------------- */
    function getStats() {
        const s = lsGet(STATS_KEY, null);
        if (!s || s.date !== todayStr()) return { date: todayStr(), sessions: 0, minutes: 0, distractions: 0 };
        return s;
    }

    function renderStats() {
        if (!statsLine) return;
        const s = getStats();
        const tpl = TJ('js_stats', '{s} sessions · {m} min focused today');
        statsLine.textContent = tpl.replace('{s}', s.sessions).replace('{m}', s.minutes);
        statsLine.style.display = s.sessions > 0 ? '' : 'none';
    }

    function recordSession(minutes) {
        const s = getStats();
        s.sessions++;
        s.minutes += minutes;
        s.distractions += distractions;
        lsSet(STATS_KEY, s);
        renderStats();
        // Guests earn local XP; logged-in XP stays server-side to avoid divergence.
        if (!window.ADHD_USER && window.Store) {
            Store.addXP(10);
        }
    }

    /* ---------------- display ---------------- */
    function updateDisplay(force) {
        const secs = currentSeconds();
        if (!force && secs === lastShownSecond) return;
        lastShownSecond = secs;

        const text = formatTime(secs);
        displayElement.textContent = text;
        if (fsTimer) fsTimer.textContent = text;
        document.title = text + ' - Focus | ADHD.lk';

        if (currentMode === 'stopwatch') {
            timerProgress.style.strokeDashoffset = 0;
        } else {
            const fraction = totalTime > 0 ? secs / totalTime : 0;
            timerProgress.style.strokeDashoffset = circleCircumference * (1 - fraction);
        }
    }

    /* ---------------- notifications ---------------- */
    function maybeRequestNotifyPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
    }

    function notify(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                new Notification(title, { body: body, icon: '/assets/icon-192.svg', silent: false });
            } catch (e) {}
        }
    }

    /* ---------------- engine ---------------- */
    function beginTicking(resumingFromReload) {
        isRunning = true;
        startBtn.textContent = TJ('js_pause', 'Pause');
        if (fsToggle) fsToggle.textContent = TJ('js_pause', 'Pause');
        clearInterval(tickInterval);
        tickInterval = setInterval(tick, 250);
        if (!resumingFromReload) saveState();
        updateDisplay(true);
    }

    function tick() {
        if (!isRunning) return;
        if (currentMode === 'stopwatch') {
            updateDisplay();
            return;
        }
        const secs = currentSeconds();
        updateDisplay();
        if (secs <= 0) {
            handleCompletion(false);
        }
    }

    function startTimer() {
        if (isRunning) return;
        maybeRequestNotifyPermission();

        if (intentionInput.value.trim() === '' && FOCUS_MODES.includes(currentMode)) {
            intentionInput.style.borderColor = 'var(--accent-primary)';
            setTimeout(() => intentionInput.style.borderColor = 'var(--bg-glass-border)', 2000);
        }

        if (currentMode === 'stopwatch') {
            stopwatchStart = Date.now();
        } else {
            if (remaining <= 0) remaining = totalTime;
            endTime = Date.now() + remaining * 1000;
        }
        beginTicking(false);
    }

    function pauseTimer() {
        if (!isRunning) return;
        if (currentMode === 'stopwatch') {
            stopwatchBase = currentSeconds();
            stopwatchStart = null;
        } else {
            remaining = currentSeconds();
        }
        isRunning = false;
        endTime = null;
        clearInterval(tickInterval);
        startBtn.textContent = TJ('js_resume', 'Resume');
        if (fsToggle) fsToggle.textContent = TJ('js_resume', 'Resume');
        saveState();
    }

    function resetTimer() {
        isRunning = false;
        clearInterval(tickInterval);
        endTime = null;
        remaining = MODES[currentMode];
        totalTime = MODES[currentMode];
        stopwatchBase = 0;
        stopwatchStart = null;
        distractions = 0;
        distractionCount.textContent = '0';
        startBtn.textContent = TJ('js_start', 'Start');
        if (fsToggle) fsToggle.textContent = TJ('js_start', 'Start');
        saveState();
        updateDisplay(true);
    }

    function handleCompletion(wasOffline) {
        isRunning = false;
        clearInterval(tickInterval);
        endTime = null;

        const wasBreak = currentMode === 'shortBreak';
        playNotificationSound(wasBreak ? 'start' : 'stop');

        if (wasBreak) {
            notify('ADHD.lk', TJ('js_break_done', 'Break over — ready for the next round?'));
            if (window.Store) Store.toast(TJ('js_break_done', 'Break over — ready for the next round?'));
            switchMode(lastFocusMode, true);
        } else {
            recordSession(Math.round(totalTime / 60));
            notify('ADHD.lk', TJ('js_session_done', 'Focus session complete! Take a break 🎉'));
            if (window.Store) Store.toast(TJ('js_session_done', 'Focus session complete! Take a break 🎉'));
            lastFocusMode = currentMode;
            switchMode('shortBreak', true);
        }
        exitFullscreenMode();
        saveState();
    }

    /* ---------------- modes ---------------- */
    function updateCustomLabel() {
        if (customBtn) {
            customBtn.textContent = Math.round(MODES.custom / 60) + 'm ✎';
        }
    }

    function switchMode(mode, keepDistractions) {
        if (isRunning) pauseTimer();
        if (FOCUS_MODES.includes(currentMode)) lastFocusMode = currentMode;

        currentMode = mode;
        remaining = MODES[mode];
        totalTime = MODES[mode];
        stopwatchBase = 0;
        stopwatchStart = null;
        endTime = null;
        isRunning = false;
        clearInterval(tickInterval);

        modeBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === mode));
        if (customRow) customRow.classList.toggle('hidden', mode !== 'custom');

        if (!keepDistractions) {
            distractions = 0;
            distractionCount.textContent = '0';
        }
        startBtn.textContent = TJ('js_start', 'Start');
        if (fsToggle) fsToggle.textContent = TJ('js_start', 'Start');
        saveState();
        updateDisplay(true);
    }

    /* ---------------- distraction log ---------------- */
    function logDistraction() {
        distractions++;
        distractionCount.textContent = distractions;
        saveState();
        logDistractionBtn.textContent = TJ('js_logged', 'Logged!');
        logDistractionBtn.style.backgroundColor = 'var(--bg-secondary)';
        setTimeout(() => {
            logDistractionBtn.textContent = TJ('js_log_distraction', 'Log Distraction');
            logDistractionBtn.style.backgroundColor = '';
        }, 1000);
    }

    /* ---------------- fullscreen focus mode ---------------- */
    async function enterFullscreenMode() {
        if (!fsOverlay) return;
        fsOverlay.classList.add('active');
        if (fsIntention) {
            fsIntention.textContent = intentionInput.value.trim() || TJ('js_focus_label', 'Focus');
        }
        if (fsToggle) fsToggle.textContent = isRunning ? TJ('js_pause', 'Pause') : TJ('js_start', 'Start');
        updateDisplay(true);
        try {
            if ('wakeLock' in navigator) {
                wakeLock = await navigator.wakeLock.request('screen');
            }
        } catch (e) {}
    }

    function exitFullscreenMode() {
        if (!fsOverlay) return;
        fsOverlay.classList.remove('active');
        if (wakeLock) { try { wakeLock.release(); } catch (e) {} wakeLock = null; }
    }

    /* ---------------- sounds ---------------- */
    function playNotificationSound(type = 'stop') {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gainNode = ctx.createGain();
            osc.connect(gainNode);
            gainNode.connect(ctx.destination);

            if (type === 'start') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);
                gainNode.gain.setValueAtTime(0, ctx.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.1, ctx.currentTime + 0.05);
                gainNode.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.5);
                osc.start();
                osc.stop(ctx.currentTime + 0.5);
            } else {
                // Long, satisfying completion bell (two-tone)
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                gainNode.gain.setValueAtTime(0, ctx.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.1, ctx.currentTime + 0.05);
                gainNode.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 2.0);
                osc.start();
                osc.stop(ctx.currentTime + 2.0);
                setTimeout(() => {
                    try {
                        const osc2 = ctx.createOscillator();
                        const gain2 = ctx.createGain();
                        osc2.connect(gain2); gain2.connect(ctx.destination);
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(880, ctx.currentTime); // A5
                        gain2.gain.setValueAtTime(0, ctx.currentTime);
                        gain2.gain.linearRampToValueAtTime(0.08, ctx.currentTime + 0.05);
                        gain2.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 1.5);
                        osc2.start();
                        osc2.stop(ctx.currentTime + 1.5);
                    } catch (e) {}
                }, 350);
            }
        } catch (e) {}
    }

    /* ---------------- events ---------------- */
    startBtn.addEventListener('click', () => {
        if (isRunning) { pauseTimer(); }
        else { playNotificationSound('start'); startTimer(); }
    });

    resetBtn.addEventListener('click', resetTimer);
    logDistractionBtn.addEventListener('click', logDistraction);

    modeBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const mode = e.currentTarget.dataset.mode;
            switchMode(mode);
            if (mode === 'custom' && customInput) {
                customInput.value = Math.round(MODES.custom / 60);
                customInput.focus();
            }
        });
    });

    if (customSetBtn) {
        customSetBtn.addEventListener('click', () => {
            let mins = parseInt(customInput.value, 10);
            if (isNaN(mins)) return;
            mins = Math.min(180, Math.max(1, mins));
            MODES.custom = mins * 60;
            lsSet(CUSTOM_KEY, mins);
            updateCustomLabel();
            switchMode('custom');
        });
    }

    intentionInput.addEventListener('change', saveState);

    if (fsBtn) fsBtn.addEventListener('click', enterFullscreenMode);
    if (fsExit) fsExit.addEventListener('click', exitFullscreenMode);
    if (fsToggle) fsToggle.addEventListener('click', () => {
        if (isRunning) { pauseTimer(); }
        else { playNotificationSound('start'); startTimer(); }
    });

    // Spacebar: start/pause (unless typing)
    document.addEventListener('keydown', (e) => {
        if (e.code !== 'Space') return;
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
        e.preventDefault();
        if (isRunning) { pauseTimer(); } else { playNotificationSound('start'); startTimer(); }
    });

    // Re-acquire wake lock when returning to a visible fullscreen session
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            updateDisplay(true);
            if (fsOverlay && fsOverlay.classList.contains('active') && isRunning && 'wakeLock' in navigator && !wakeLock) {
                navigator.wakeLock.request('screen').then(l => { wakeLock = l; }).catch(() => {});
            }
        }
    });

    window.addEventListener('beforeunload', saveState);

    // Initialize
    restoreState();
    renderStats();
    updateDisplay(true);
});
