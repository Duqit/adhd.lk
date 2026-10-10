// i18n lookup with English fallback
var TJ = function (k, fb) { return (window.ADHD_I18N && window.ADHD_I18N[k]) || fb; };

// global.js
document.addEventListener('DOMContentLoaded', () => {
    // Inject Brain Dump UI into the body
    const dumpHTML = `
        <!-- Floating Menu Container -->
        <div class="fab-container" id="fab-menu">
            <!-- Main Toggle Button -->
            <button id="main-fab" class="fab" title="Open Tools" aria-label="Open Tools">
                ➕
            </button>

            <!-- Brain Dump Floating Action Button -->
            <button id="brain-dump-fab" class="fab sub-fab" title="Brain Dump" aria-label="Quick Brain Dump">
                🧠
            </button>

            <!-- Emotional SOS Button -->
            <button id="sos-fab" class="fab sub-fab sos-fab" title="SOS Breathing" aria-label="Emotional SOS Breathing">
                🌬️
            </button>
        </div>

        <!-- Breathing Modal -->
        <div id="breathing-modal" class="modal-overlay hidden">
            <div class="modal-content glass-card" style="text-align: center;">
                <div class="modal-header">
                    <h3>${TJ('js_breath_title', 'Guided Breathing')}</h3>
                    <button id="close-breathing" class="icon-btn">&times;</button>
                </div>
                <p class="modal-subtitle">${TJ('js_breath_sub', 'Follow the circle to regulate your nervous system.')}</p>
                
                <div class="breathing-container" style="position: relative; width: 200px; height: 200px; margin: 2rem auto;">
                    <div id="breathing-circle" style="width: 100px; height: 100px; background: var(--accent-primary); border-radius: 50%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.6; transition: all linear;"></div>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; font-size: 1.2rem; z-index: 10; text-shadow: 0 2px 4px rgba(0,0,0,0.5);" id="breathing-instruction">${TJ('js_breath_ready', 'Ready')}</div>
                </div>

                <button id="start-breathing" class="btn btn-primary">${TJ('js_breath_start', 'Start 4-7-8 Breathing')}</button>
            </div>
        </div>

        <!-- Brain Dump Modal -->
        <div id="brain-dump-modal" class="modal-overlay hidden">
            <div class="modal-content glass-card">
                <div class="modal-header">
                    <h3>${TJ('js_dump_title', 'Quick Brain Dump')}</h3>
                    <button id="close-dump" class="icon-btn">&times;</button>
                </div>
                <p class="modal-subtitle">${TJ('js_dump_sub', "Don't filter or categorize. Just get it out of your head.")}</p>
                <textarea id="dump-text" placeholder="${TJ('js_dump_ph', "What's on your mind?...")}" rows="6"></textarea>
                <div class="modal-actions">
                    <button id="save-dump" class="btn btn-primary">${TJ('js_dump_save', 'Save to Inbox')}</button>
                    <span id="dump-feedback" class="feedback-msg hidden">${TJ('js_dump_saved', 'Saved!')}</span>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', dumpHTML);

    // CSS for Brain Dump (injected so it works everywhere without editing style.css, though moving to style.css is better. We will do both for safety)
    const fab = document.getElementById('brain-dump-fab');
    const modal = document.getElementById('brain-dump-modal');
    const closeBtn = document.getElementById('close-dump');
    const saveBtn = document.getElementById('save-dump');
    const dumpText = document.getElementById('dump-text');
    const feedback = document.getElementById('dump-feedback');

    const mainFab = document.getElementById('main-fab');
    const fabMenu = document.getElementById('fab-menu');
    
    if (mainFab && fabMenu) {
        mainFab.addEventListener('click', () => {
            fabMenu.classList.toggle('expanded');
        });
    }

    fab.addEventListener('click', () => {
        fabMenu.classList.remove('expanded');
        modal.classList.remove('hidden');
        dumpText.value = ''; // Clear previous
        feedback.classList.add('hidden');
        setTimeout(() => dumpText.focus(), 100); // Auto-focus for 5-second rule
    });

    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Close on click outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    // Save Dump
    saveBtn.addEventListener('click', () => {
        const text = dumpText.value.trim();
        if (!text) return;

        // Save to local storage for now (fastest)
        const dumps = JSON.parse(localStorage.getItem('brain_dumps') || '[]');
        dumps.push({
            id: Date.now(),
            text: text,
            date: new Date().toISOString()
        });
        localStorage.setItem('brain_dumps', JSON.stringify(dumps));

        feedback.classList.remove('hidden');
        dumpText.value = '';
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 1000);
    });

    // --- Breathing Logic ---
    const sosFab = document.getElementById('sos-fab');
    const breathModal = document.getElementById('breathing-modal');
    const closeBreathBtn = document.getElementById('close-breathing');
    const startBreathBtn = document.getElementById('start-breathing');
    const breathCircle = document.getElementById('breathing-circle');
    const breathInstruction = document.getElementById('breathing-instruction');
    let breathInterval = null;
    let isBreathing = false;

    sosFab.addEventListener('click', () => {
        fabMenu.classList.remove('expanded');
        breathModal.classList.remove('hidden');
    });

    closeBreathBtn.addEventListener('click', () => {
        breathModal.classList.add('hidden');
        stopBreathing();
    });

    breathModal.addEventListener('click', (e) => {
        if (e.target === breathModal) {
            breathModal.classList.add('hidden');
            stopBreathing();
        }
    });

    function stopBreathing() {
        isBreathing = false;
        clearTimeout(breathInterval);
        breathInstruction.textContent = TJ('js_breath_ready', 'Ready');
        breathCircle.style.transitionDuration = '0.5s';
        breathCircle.style.transform = 'translate(-50%, -50%) scale(1)';
        startBreathBtn.textContent = TJ('js_breath_start', 'Start 4-7-8 Breathing');
    }

    function runBreathingCycle() {
        if (!isBreathing) return;
        
        // Inhale (4s)
        breathInstruction.textContent = TJ('js_inhale', 'Inhale');
        breathCircle.style.transitionDuration = '4s';
        breathCircle.style.transform = 'translate(-50%, -50%) scale(2)';
        
        breathInterval = setTimeout(() => {
            if (!isBreathing) return;
            // Hold (7s)
            breathInstruction.textContent = TJ('js_hold', 'Hold');
            
            breathInterval = setTimeout(() => {
                if (!isBreathing) return;
                // Exhale (8s)
                breathInstruction.textContent = TJ('js_exhale', 'Exhale');
                breathCircle.style.transitionDuration = '8s';
                breathCircle.style.transform = 'translate(-50%, -50%) scale(1)';
                
                breathInterval = setTimeout(() => {
                    if (isBreathing) runBreathingCycle();
                }, 8000);
            }, 7000);
        }, 4000);
    }

    startBreathBtn.addEventListener('click', () => {
        if (isBreathing) {
            stopBreathing();
        } else {
            isBreathing = true;
            startBreathBtn.textContent = TJ('js_breath_stop', 'Stop');
            runBreathingCycle();
        }
    });

    // --- Navbar Hide on Scroll ---
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        let lastScrollY = window.scrollY;
        
        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;

            // Never hide the navbar while the mobile menu is open
            if (navbar.classList.contains('open')) {
                lastScrollY = currentScrollY;
                return;
            }

            // Only hide if we've scrolled down a bit to avoid jitter at the top
            if (currentScrollY > 50) {
                if (currentScrollY > lastScrollY) {
                    // Scrolling down
                    navbar.classList.add('navbar-hidden');
                } else {
                    // Scrolling up
                    navbar.classList.remove('navbar-hidden');
                }
            } else {
                // At the top, always show
                navbar.classList.remove('navbar-hidden');
            }
            
            lastScrollY = currentScrollY;
        }, { passive: true });
    }
});
