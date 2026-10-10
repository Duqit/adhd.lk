<?php
require_once "includes/i18n.php";
$page_title = t('seo_timer_title');
$page_desc = t('seo_timer_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 3rem; margin-bottom: 0.5rem;"><?php echo t('timer_title'); ?></h1>
            <p style="color: var(--text-secondary);"><?php echo t('timer_sub'); ?></p>
        </div>

        <div style="display: flex; gap: 2rem; flex-wrap: wrap; justify-content: center;">
            
            <!-- Enhanced Focus Timer Section -->
            <section class="pomodoro-container glass-card" id="pomodoro" style="flex: 1; min-width: 320px; max-width: 500px; margin: 0;">
                
                <div class="intention-setting" style="margin-bottom: 1.5rem;">
                    <input type="text" id="focus-intention" placeholder="<?php echo t('timer_intention_ph'); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--bg-glass-border); background: var(--bg-input); color: var(--text-primary); outline: none; text-align: center;">
                </div>

                <div class="timer-modes" id="timer-modes">
                    <button class="mode-btn" data-mode="15">15m</button>
                    <button class="mode-btn active" data-mode="25">25m</button>
                    <button class="mode-btn" data-mode="50">50m</button>
                    <button class="mode-btn" data-mode="custom">30m ✎</button>
                    <button class="mode-btn" data-mode="shortBreak"><?php echo t('timer_break'); ?></button>
                    <button class="mode-btn" data-mode="stopwatch"><?php echo t('timer_stopwatch'); ?></button>
                </div>

                <div id="custom-row" class="hidden" style="display: flex; gap: 0.5rem; justify-content: center; align-items: center; margin-bottom: 1.5rem;">
                    <input type="number" id="custom-mins" min="1" max="180" value="30" class="add-task-input" style="text-align: center; width: 90px; flex: 0 0 90px;" aria-label="<?php echo t('timer_custom_ph'); ?>">
                    <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('timer_custom_ph'); ?></span>
                    <button type="button" id="custom-set" class="btn btn-secondary" style="padding: 0.5rem 1.1rem; font-size: 0.85rem;"><?php echo t('timer_set'); ?></button>
                </div>
                
                <div class="visual-timer-wrapper" style="position: relative; width: 250px; height: 250px; margin: 0 auto 2rem auto;">
                    <svg width="250" height="250" viewBox="0 0 250 250" style="transform: rotate(-90deg);">
                        <circle cx="125" cy="125" r="115" fill="none" stroke="var(--bg-secondary)" stroke-width="10"></circle>
                        <circle id="timer-progress" cx="125" cy="125" r="115" fill="none" stroke="var(--accent-primary)" stroke-width="10" stroke-dasharray="722.5" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear;"></circle>
                    </svg>
                    <div class="timer-display" id="timer-display" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); margin: 0; font-size: 3.5rem;">
                        25:00
                    </div>
                </div>
                
                <div class="timer-controls">
                    <button class="btn btn-primary btn-large" id="start-btn"><?php echo t('timer_start'); ?></button>
                    <button class="btn btn-secondary btn-large" id="reset-btn"><?php echo t('timer_reset'); ?></button>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                    <button id="log-distraction-btn" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;"><?php echo t('timer_log_distraction'); ?></button>
                    <span id="distraction-count" style="color: var(--text-secondary); font-size: 0.85rem;">0</span>
                    <button id="fs-btn" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;" title="Fullscreen focus mode">⛶ <?php echo t('timer_fullscreen'); ?></button>
                </div>

                <p id="focus-stats" style="margin-top: 1.25rem; color: var(--accent-secondary); font-size: 0.9rem; font-weight: 600; display: none;"></p>
            </section>

            <!-- Ambient Sound Mixer Section -->
            <section class="glass-card" style="flex: 1; min-width: 320px; max-width: 500px; margin: 0; padding: 1.5rem;" id="ambient-mixer">
                <h3 style="text-align: center; margin-bottom: 1.5rem;"><?php echo t('timer_mixer'); ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem; text-align: center; margin-bottom: 2rem;"><?php echo t('timer_mixer_sub'); ?></p>
                
                <div class="sound-control" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <span style="font-size: 1.5rem; width: 30px; text-align: center;">🟤</span>
                    <div style="flex: 1;">
                        <label style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                            <span><?php echo t('timer_brown'); ?></span>
                        </label>
                        <input type="range" id="vol-brown" min="0" max="1" step="0.01" value="0" style="width: 100%;">
                    </div>
                </div>

                <div class="sound-control" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <span style="font-size: 1.5rem; width: 30px; text-align: center;">🌧️</span>
                    <div style="flex: 1;">
                        <label style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                            <span><?php echo t('timer_rain'); ?></span>
                        </label>
                        <input type="range" id="vol-rain" min="0" max="1" step="0.01" value="0" style="width: 100%;">
                    </div>
                </div>

                <div class="sound-control" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <span style="font-size: 1.5rem; width: 30px; text-align: center;">🌊</span>
                    <div style="flex: 1;">
                        <label style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                            <span><?php echo t('timer_ocean'); ?></span>
                        </label>
                        <input type="range" id="vol-ocean" min="0" max="1" step="0.01" value="0" style="width: 100%;">
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button id="stop-all-sounds" class="btn btn-secondary" style="font-size: 0.85rem;"><?php echo t('timer_stop_all'); ?></button>
                </div>
            </section>
        </div>

        <p style="text-align: center; margin-top: 2.5rem; color: var(--text-secondary); font-size: 0.9rem;">
            <?php echo t('timer_why_25'); ?>
        </p>

    </main>

    <!-- Fullscreen distraction-free focus mode (reuses runner styles) -->
    <div class="routine-runner" id="focus-fs">
        <p style="color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 2px;">ADHD.lk</p>
        <h2 class="runner-title" id="fs-intention" style="max-width: 90%; overflow-wrap: anywhere;"></h2>
        <div class="runner-timer" id="fs-timer">25:00</div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-primary btn-large" id="fs-toggle"><?php echo t('timer_start'); ?></button>
            <button class="btn btn-secondary btn-large" id="fs-exit"><?php echo t('js_exit_fs'); ?></button>
        </div>
    </div>

    <script src="/js/pomodoro.js?v=20260828a"></script>
    <script src="/js/ambient.js?v=20260828a"></script>
<?php require_once 'includes/footer.php'; ?>
