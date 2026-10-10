// i18n lookup with English fallback
var TJ = function (k, fb) { return (window.ADHD_I18N && window.ADHD_I18N[k]) || fb; };

// js/routines.js

document.addEventListener('DOMContentLoaded', () => {
    // Form toggle logic
    const btnCreate = document.getElementById('btn-create-routine');
    const formContainer = document.getElementById('create-routine-form');
    const btnCancel = document.getElementById('btn-cancel-routine');
    const btnAddStep = document.getElementById('btn-add-step');
    const stepsContainer = document.getElementById('steps-container');

    if (btnCreate && formContainer) {
        btnCreate.addEventListener('click', () => {
            formContainer.classList.remove('hidden');
            btnCreate.style.display = 'none';
        });
    }

    if (btnCancel && formContainer) {
        btnCancel.addEventListener('click', () => {
            formContainer.classList.add('hidden');
            if (btnCreate) btnCreate.style.display = 'block';
        });
    }

    if (btnAddStep && stepsContainer) {
        btnAddStep.addEventListener('click', () => {
            const stepHtml = `
                <div class="step-input-group">
                    <input type="text" name="step_title[]" placeholder="${TJ('js_step_ph', 'e.g., Next step')}" required>
                    <input type="number" name="step_duration[]" placeholder="${TJ('js_min_ph', 'Min')}" min="1" required>
                    <button type="button" class="btn btn-secondary btn-remove-step" style="padding: 0 1rem; color: #ef4444;">&times;</button>
                </div>
            `;
            stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
            
            // Add listener to the new remove button
            const removeBtns = stepsContainer.querySelectorAll('.btn-remove-step');
            const lastBtn = removeBtns[removeBtns.length - 1];
            lastBtn.addEventListener('click', function() {
                this.parentElement.remove();
            });
        });
    }
});

// --- Routine Runner Logic ---
let currentRoutine = null;
let currentStepIndex = 0;
let timeRemaining = 0;
let timerInterval = null;
let isPaused = false;

const runnerUi = document.getElementById('routine-runner');
const rName = document.getElementById('runner-routine-name');
const rTitle = document.getElementById('runner-step-title');
const rTimer = document.getElementById('runner-timer');
const rNext = document.getElementById('runner-next-step');
const rProgress = document.getElementById('runner-progress');

const btnPlay = document.getElementById('runner-btn-play');
const btnSkip = document.getElementById('runner-btn-skip');
const btnQuit = document.getElementById('runner-btn-quit');

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

// Make sure it's globally available for the inline onclick handler
window.startRoutine = function(routineData) {
    if (!routineData || !routineData.steps || routineData.steps.length === 0) {
        alert(TJ('js_no_steps', 'This routine has no steps!'));
        return;
    }
    
    currentRoutine = routineData;
    currentStepIndex = 0;
    isPaused = false;
    
    runnerUi.classList.add('active');
    rName.textContent = routineData.name;
    
    loadStep();
}

function loadStep() {
    if (currentStepIndex >= currentRoutine.steps.length) {
        finishRoutine();
        return;
    }
    
    const step = currentRoutine.steps[currentStepIndex];
    timeRemaining = step.duration_minutes * 60;
    
    rTitle.textContent = step.title;
    rTimer.textContent = formatTime(timeRemaining);
    
    // Update next step text
    if (currentStepIndex + 1 < currentRoutine.steps.length) {
        const next = currentRoutine.steps[currentStepIndex + 1];
        rNext.textContent = TJ('js_next', 'Next') + ': ' + next.title + ' (' + next.duration_minutes + 'm)';
        btnSkip.textContent = TJ('js_next_step', 'Next Step');
    } else {
        rNext.textContent = TJ('js_next_finish', 'Next: Finish!');
        btnSkip.textContent = TJ('js_finish_routine', 'Finish Routine');
    }
    
    // Update progress bar
    const progressPercent = (currentStepIndex / currentRoutine.steps.length) * 100;
    rProgress.style.width = `${progressPercent}%`;
    
    btnPlay.textContent = TJ('js_pause', 'Pause');
    btnPlay.style.display = 'block';
    isPaused = false;
    
    clearInterval(timerInterval);
    timerInterval = setInterval(tick, 1000);
    
    playAudioNotification('start');
}

function tick() {
    if (isPaused) return;
    
    timeRemaining--;
    if (timeRemaining <= 0) {
        timeRemaining = 0;
        rTimer.textContent = formatTime(timeRemaining);
        clearInterval(timerInterval);
        playAudioNotification('stop');
        
        // Auto advance after 2 seconds
        setTimeout(() => {
            currentStepIndex++;
            loadStep();
        }, 2000);
    } else {
        rTimer.textContent = formatTime(timeRemaining);
    }
}

function finishRoutine() {
    clearInterval(timerInterval);
    rProgress.style.width = '100%';
    rTitle.textContent = TJ('js_routine_complete', 'Routine Complete!');
    rTimer.textContent = "00:00";
    rNext.textContent = TJ('js_great_job', 'Great job!');
    btnPlay.style.display = 'none';
    btnSkip.textContent = TJ('js_return', 'Return to Dashboard');
    
    // Confetti or play sound
    playAudioNotification('stop');
    
    btnSkip.onclick = () => {
        window.location.reload();
    };
}

if (btnPlay) {
    btnPlay.addEventListener('click', () => {
        isPaused = !isPaused;
        btnPlay.textContent = isPaused ? TJ('js_resume', 'Resume') : TJ('js_pause', 'Pause');
    });
}

if (btnSkip) {
    btnSkip.addEventListener('click', () => {
        if (currentStepIndex >= currentRoutine.steps.length) {
            window.location.reload();
            return;
        }
        clearInterval(timerInterval);
        currentStepIndex++;
        loadStep();
    });
}

if (btnQuit) {
    btnQuit.addEventListener('click', () => {
        if (confirm(TJ('js_confirm_quit', 'Are you sure you want to quit this routine?'))) {
            clearInterval(timerInterval);
            runnerUi.classList.remove('active');
            window.location.reload();
        }
    });
}

// Minimal Audio System fallback if ambient.js isn't loaded here
function playAudioNotification(type) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        
        osc.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        if (type === 'start') {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(440, audioCtx.currentTime); // A4
            osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.1);
            gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.5, audioCtx.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.3);
        } else if (type === 'stop') {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
            gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.5, audioCtx.currentTime + 0.1);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 1.5);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 1.5);
        }
    } catch(e) {
        console.error("Audio not supported");
    }
}
