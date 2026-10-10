<?php
require_once "includes/i18n.php";
$page_title = t('seo_asrs_title');
$page_desc = t('seo_asrs_desc');

// FAQPage rich-result schema built from the localized FAQ entries
$faq_items = [];
foreach (t_arr('asrs_faq') as $f) {
    $faq_items[] = [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ];
}
$page_jsonld = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $faq_items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

require_once "includes/header.php";
?>

    <main class="main-content container">
        <div class="section-head" style="margin-bottom: 2rem;">
            <span class="chip"><?php echo t('asrs_chip'); ?></span>
            <h1 style="margin-top: 1rem;"><?php echo t('asrs_title'); ?></h1>
            <p><?php echo t('asrs_intro'); ?></p>
            <?php if (t('asrs_translation_note')): ?>
                <p style="font-size: 0.85rem; color: var(--text-faint); margin-top: 0.75rem;"><?php echo t('asrs_translation_note'); ?></p>
            <?php endif; ?>
        </div>

        <div class="glass-card quiz-card" id="quiz-area">
            <h3 style="margin-bottom: 0.25rem;"><?php echo t('asrs_part_a'); ?> <span style="color: var(--text-secondary); font-weight: 400; font-size: 0.9rem;"><?php echo t('asrs_part_a_sub'); ?></span></h3>
            <div id="part-a"></div>

            <h3 style="margin: 2rem 0 0.25rem 0;"><?php echo t('asrs_part_b'); ?> <span style="color: var(--text-secondary); font-weight: 400; font-size: 0.9rem;"><?php echo t('asrs_part_b_sub'); ?></span></h3>
            <div id="part-b"></div>

            <div style="text-align: center; margin-top: 2rem;">
                <button class="btn btn-primary btn-large" id="quiz-submit"><?php echo t('asrs_submit'); ?></button>
                <p id="quiz-warn" class="hidden" style="color: var(--color-warn); margin-top: 1rem; font-size: 0.9rem;"><?php echo t('asrs_missing'); ?> <span id="quiz-missing"></span> <?php echo t('asrs_left'); ?></p>
            </div>
        </div>

        <div class="glass-card quiz-card hidden" id="result-area" style="margin-top: 2rem;">
            <div class="result-box">
                <p style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.8rem; color: var(--text-secondary);"><?php echo t('asrs_score_label'); ?></p>
                <div class="result-score"><span id="score-a">0</span>/6</div>
                <h2 id="result-headline" style="margin-top: 1rem;"></h2>
                <p id="result-text" style="color: var(--text-secondary); max-width: 560px; margin: 0 auto;"></p>
            </div>
            <div style="margin-top: 1.5rem; text-align: center; color: var(--text-secondary); font-size: 0.95rem;">
                <p><?php echo t('asrs_partb_result_1'); ?> <strong id="score-b">0</strong><?php echo t('asrs_partb_result_2'); ?></p>
            </div>
            <div style="margin-top: 2rem;">
                <h3 style="font-size: 1.05rem;"><?php echo t('asrs_whatnow'); ?></h3>
                <ul style="margin-left: 1.4rem; color: var(--text-secondary); line-height: 1.9;">
                    <li><?php echo t('asrs_b1'); ?></li>
                    <li><?php echo t('asrs_b2'); ?></li>
                    <li><?php echo t('asrs_b3'); ?></li>
                    <li><?php echo t('asrs_b4'); ?></li>
                </ul>
            </div>
            <div style="text-align: center; margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <button class="btn btn-secondary" onclick="window.print()"><?php echo t('asrs_print'); ?></button>
                <button class="btn btn-secondary" id="quiz-retake"><?php echo t('asrs_retake'); ?></button>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo urlencode(t('seo_asrs_title') . ' — https://adhd.lk/assessment'); ?>">📤 <?php echo t('share_test'); ?></a>
            </div>
        </div>

        <!-- FAQ -->
        <section class="glass-card quiz-card" style="margin-top: 2rem;">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.25rem;"><?php echo t('asrs_faq_title'); ?></h2>
            <?php foreach (t_arr('asrs_faq') as $f): ?>
                <details class="faq-item">
                    <summary><?php echo htmlspecialchars($f['q']); ?></summary>
                    <p><?php echo htmlspecialchars($f['a']); ?></p>
                </details>
            <?php endforeach; ?>
            <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);"><?php echo t('asrs_faq_deep'); ?></p>
        </section>

        <div class="disclaimer quiz-card">
            <?php echo t('asrs_disclaimer'); ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ASRS v1.1 — 18 items. 'th' = minimum answer index counting as a positive (shaded) response.
        // Options: 0 Never, 1 Rarely, 2 Sometimes, 3 Often, 4 Very Often
        // Shaded at Sometimes+: items 1,2,3,9,12,16,18 · Shaded at Often+: the rest.
        const OPTIONS = <?php echo json_encode([t('asrs_opt_never'), t('asrs_opt_rarely'), t('asrs_opt_sometimes'), t('asrs_opt_often'), t('asrs_opt_veryoften')], JSON_UNESCAPED_UNICODE); ?>;
        const QUESTION_TEXT = <?php echo json_encode(t_arr('asrs_questions'), JSON_UNESCAPED_UNICODE); ?>;
        const THRESHOLDS = [2, 2, 2, 3, 3, 3, 3, 3, 2, 3, 3, 2, 3, 3, 3, 2, 3, 2];
        const L = {
            posHeadline: <?php echo json_encode(t('asrs_pos_headline'), JSON_UNESCAPED_UNICODE); ?>,
            posText: <?php echo json_encode(t('asrs_pos_text'), JSON_UNESCAPED_UNICODE); ?>,
            negHeadline: <?php echo json_encode(t('asrs_neg_headline'), JSON_UNESCAPED_UNICODE); ?>,
            negText: <?php echo json_encode(t('asrs_neg_text'), JSON_UNESCAPED_UNICODE); ?>
        };

        const answers = new Array(18).fill(null);
        const partA = document.getElementById('part-a');
        const partB = document.getElementById('part-b');

        QUESTION_TEXT.forEach((q, i) => {
            const wrap = document.createElement('div');
            wrap.className = 'quiz-q';
            const h = document.createElement('h4');
            h.textContent = (i + 1) + '. ' + q;
            const opts = document.createElement('div');
            opts.className = 'quiz-opts';
            OPTIONS.forEach((o, oi) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'quiz-opt';
                b.dataset.q = i; b.dataset.v = oi;
                b.textContent = o;
                opts.appendChild(b);
            });
            wrap.appendChild(h); wrap.appendChild(opts);
            (i < 6 ? partA : partB).appendChild(wrap);
        });

        document.querySelectorAll('.quiz-opt').forEach(btn => {
            btn.addEventListener('click', () => {
                const q = parseInt(btn.dataset.q, 10);
                answers[q] = parseInt(btn.dataset.v, 10);
                btn.parentElement.querySelectorAll('.quiz-opt').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                document.getElementById('quiz-warn').classList.add('hidden');
            });
        });

        document.getElementById('quiz-submit').addEventListener('click', () => {
            const missing = answers.filter(a => a === null).length;
            if (missing > 0) {
                document.getElementById('quiz-missing').textContent = missing;
                document.getElementById('quiz-warn').classList.remove('hidden');
                return;
            }

            let scoreA = 0, scoreB = 0;
            THRESHOLDS.forEach((th, i) => {
                if (answers[i] >= th) { if (i < 6) scoreA++; else scoreB++; }
            });

            document.getElementById('score-a').textContent = scoreA;
            document.getElementById('score-b').textContent = scoreB;

            const headline = document.getElementById('result-headline');
            const text = document.getElementById('result-text');
            if (scoreA >= 4) {
                headline.textContent = L.posHeadline;
                text.textContent = L.posText;
            } else {
                headline.textContent = L.negHeadline;
                text.textContent = L.negText;
            }

            document.getElementById('result-area').classList.remove('hidden');
            document.getElementById('result-area').scrollIntoView({ behavior: 'smooth' });
        });

        document.getElementById('quiz-retake').addEventListener('click', () => {
            answers.fill(null);
            document.querySelectorAll('.quiz-opt').forEach(b => b.classList.remove('selected'));
            document.getElementById('result-area').classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    </script>

    <?php require_once 'includes/footer.php'; ?>
