<?php
require_once "includes/i18n.php";
$page_title = t('seo_calm_title');
$page_desc = t('seo_calm_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <div class="section-head" style="margin-bottom: 2rem;">
            <span class="chip"><?php echo t('calm_chip'); ?></span>
            <h1 style="margin-top: 1rem;"><?php echo t('calm_title'); ?></h1>
            <p><?php echo t('calm_sub'); ?></p>
        </div>

        <div style="display: flex; gap: 2rem; flex-wrap: wrap; justify-content: center; width: 100%;">

            <!-- Breathing exercise -->
            <section class="glass-card" style="flex: 1; min-width: 320px; max-width: 520px; text-align: center;">
                <h3><?php echo t('calm_breathing'); ?></h3>
                <div class="timer-modes" style="margin-bottom: 0.5rem;">
                    <button class="mode-btn active" data-pattern="478"><?php echo t('calm_478'); ?></button>
                    <button class="mode-btn" data-pattern="box"><?php echo t('calm_box'); ?></button>
                </div>

                <div class="breathe-stage">
                    <div class="breathe-circle" id="b-circle"></div>
                    <div class="breathe-label" id="b-label"><?php echo t('calm_ready'); ?></div>
                </div>

                <div style="color: var(--text-secondary); font-size: 0.9rem; min-height: 1.5em;" id="b-round"></div>

                <div class="timer-controls">
                    <button class="btn btn-primary btn-large" id="b-start"><?php echo t('calm_start'); ?></button>
                </div>
                <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 1.5rem;"><?php echo t('calm_desc'); ?></p>
            </section>

            <!-- 5-4-3-2-1 grounding -->
            <section class="glass-card" style="flex: 1; min-width: 320px; max-width: 520px;">
                <h3 style="text-align: center;"><?php echo t('calm_ground_t'); ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.92rem; text-align: center; margin-bottom: 1.5rem;"><?php echo t('calm_ground_sub'); ?></p>
                <ul class="task-list" id="ground-list">
                    <li class="task-item ground-step"><input type="checkbox" class="task-checkbox"><span class="task-title"><strong>5</strong> <?php echo t('calm_see'); ?></span></li>
                    <li class="task-item ground-step"><input type="checkbox" class="task-checkbox"><span class="task-title"><strong>4</strong> <?php echo t('calm_touch'); ?></span></li>
                    <li class="task-item ground-step"><input type="checkbox" class="task-checkbox"><span class="task-title"><strong>3</strong> <?php echo t('calm_hear'); ?></span></li>
                    <li class="task-item ground-step"><input type="checkbox" class="task-checkbox"><span class="task-title"><strong>2</strong> <?php echo t('calm_smell'); ?></span></li>
                    <li class="task-item ground-step"><input type="checkbox" class="task-checkbox"><span class="task-title"><strong>1</strong> <?php echo t('calm_taste'); ?></span></li>
                </ul>
                <p id="ground-done" class="hidden" style="text-align: center; color: var(--color-break); font-weight: 600; margin-top: 1.5rem;"><?php echo t('calm_done_msg'); ?></p>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <button class="btn btn-secondary" id="ground-reset" style="font-size: 0.85rem;"><?php echo t('calm_reset'); ?></button>
                </div>
            </section>
        </div>

        <div class="disclaimer" style="max-width: 720px;">
            <?php echo t('calm_crisis'); ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const L = {
            inhale: <?php echo json_encode(t('calm_inhale'), JSON_UNESCAPED_UNICODE); ?>,
            hold: <?php echo json_encode(t('calm_hold'), JSON_UNESCAPED_UNICODE); ?>,
            exhale: <?php echo json_encode(t('calm_exhale'), JSON_UNESCAPED_UNICODE); ?>,
            ready: <?php echo json_encode(t('calm_ready'), JSON_UNESCAPED_UNICODE); ?>,
            start: <?php echo json_encode(t('calm_start'), JSON_UNESCAPED_UNICODE); ?>,
            stop: <?php echo json_encode(t('calm_stop'), JSON_UNESCAPED_UNICODE); ?>,
            round: <?php echo json_encode(t('calm_round'), JSON_UNESCAPED_UNICODE); ?>
        };

        /* ---- Breathing ---- */
        const PATTERNS = {
            '478': [ { label: L.inhale, secs: 4, scale: 2 }, { label: L.hold, secs: 7, scale: 2 }, { label: L.exhale, secs: 8, scale: 1 } ],
            'box': [ { label: L.inhale, secs: 4, scale: 2 }, { label: L.hold, secs: 4, scale: 2 }, { label: L.exhale, secs: 4, scale: 1 }, { label: L.hold, secs: 4, scale: 1 } ]
        };
        let pattern = '478';
        let running = false;
        let phaseTimer = null;
        let round = 0;

        const circle = document.getElementById('b-circle');
        const label = document.getElementById('b-label');
        const roundEl = document.getElementById('b-round');
        const startBtn = document.getElementById('b-start');

        document.querySelectorAll('.mode-btn[data-pattern]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.mode-btn[data-pattern]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                pattern = btn.dataset.pattern;
                stop();
            });
        });

        function stop() {
            running = false;
            clearTimeout(phaseTimer);
            round = 0;
            label.textContent = L.ready;
            roundEl.textContent = '';
            circle.style.transitionDuration = '0.5s';
            circle.style.transform = 'scale(1)';
            startBtn.textContent = L.start;
        }

        function runPhase(idx) {
            if (!running) return;
            const phases = PATTERNS[pattern];
            if (idx === 0) {
                round++;
                roundEl.textContent = L.round + ' ' + round;
            }
            const p = phases[idx];
            label.textContent = p.label + ' · ' + p.secs + 's';
            circle.style.transitionDuration = p.secs + 's';
            circle.style.transform = 'scale(' + p.scale + ')';
            phaseTimer = setTimeout(() => runPhase((idx + 1) % phases.length), p.secs * 1000);
        }

        startBtn.addEventListener('click', () => {
            if (running) { stop(); return; }
            running = true;
            startBtn.textContent = L.stop;
            runPhase(0);
        });

        /* ---- Grounding ---- */
        const checks = document.querySelectorAll('#ground-list .task-checkbox');
        const doneMsg = document.getElementById('ground-done');
        checks.forEach(c => c.addEventListener('change', () => {
            c.closest('.task-item').classList.toggle('completed', c.checked);
            const allDone = Array.from(checks).every(x => x.checked);
            doneMsg.classList.toggle('hidden', !allDone);
        }));
        document.getElementById('ground-reset').addEventListener('click', () => {
            checks.forEach(c => { c.checked = false; c.closest('.task-item').classList.remove('completed'); });
            doneMsg.classList.add('hidden');
        });
    });
    </script>

    <?php require_once 'includes/footer.php'; ?>
