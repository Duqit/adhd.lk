<?php
require_once "includes/i18n.php";
$page_title = 'Editorial Policy — How We Write, Source and Correct | ADHD.lk';
$page_desc = 'How ADHD.lk chooses topics, which sources it uses, why its content is not clinically reviewed, and how to report an error. Written for readers and for the clinicians who check us.';
$page_canonical = 'https://adhd.lk/editorial-policy';
// English-only for now: no ?lang= duplicates and no hreflang until it is translated.
$skip_hreflang = true;
$page_lang = 'en';

$sections = [
    [
        'h' => 'Our principles',
        'b' => '<ul>
                <li><strong>Plain language.</strong> If a reader needs a dictionary, we have failed.</li>
                <li><strong>Sources you can check.</strong> Claims about ADHD link to the research or the institution they came from.</li>
                <li><strong>Honest about limits.</strong> Where the evidence is thin, or where something is a community term rather than a clinical one, we say so in the article.</li>
                <li><strong>Free and independent.</strong> No ads, no sponsorship, no paid placements, no funding from pharmaceutical companies or clinics.</li>
                </ul>',
    ],
    [
        'h' => 'How topics are chosen',
        'b' => '<p>Topics come from what people actually search for and ask about — the questions that bring readers to the site, gaps where no good plain-language answer exists in a given language, and requests sent to <a href="mailto:support@adhd.lk">support@adhd.lk</a>. We deliberately cover ordinary daily problems (starting tasks, sleep, cleaning, relationships, driving) rather than only clinical overviews, because that is where most people are stuck.</p>',
    ],
    [
        'h' => 'Sources we rely on',
        'b' => '<ul>
                <li><strong>World Health Organization</strong> — including the Adult ADHD Self-Report Scale (ASRS v1.1) used on our <a href="/assessment">self-test</a>.</li>
                <li><strong>US Centers for Disease Control and Prevention (CDC)</strong> and the <strong>National Institute of Mental Health (NIMH)</strong>.</li>
                <li><strong>UK NICE guideline NG87</strong> (ADHD: diagnosis and management) and the patient information of established ADHD organisations such as CHADD and ADDA.</li>
                <li><strong>Peer-reviewed research</strong>, located through PubMed and similar databases.</li>
                <li><strong>Sri Lankan public institutions</strong> for the Sri Lanka-specific guides — the National Institute of Mental Health, the Ministry of Health, teaching and district hospitals, and national helplines.</li>
                </ul>
                <p>Where a claim is contested or the research is weak, the article says so rather than presenting it as settled.</p>',
    ],
    [
        'h' => 'How articles are written',
        'b' => '<p>Articles are researched, drafted and edited by the ADHD.lk team, with AI writing tools used in the drafting stage. Everything published is edited before it goes live, and responsibility for what appears on this site rests with me, <strong>Nishnath Thaweesha</strong>.</p>
                <p>Each article shows its published date and, where it has been revised, its updated date. Articles are revised when guidance changes, when a reader points out a problem, or when we can explain something better.</p>',
    ],
    [
        'h' => 'Medical review status',
        'b' => '<p><strong>Our content is not reviewed by a clinician.</strong> We state this plainly rather than implying medical authority we do not have. Articles are written from published sources by someone who lives with ADHD, not by a doctor.</p>
                <p>We would welcome clinical review. <strong>If you are a psychiatrist, psychologist, paediatrician or other qualified professional willing to review material, wherever you practise — reviewers who read Sinhala or Tamil are especially welcome — please get in touch at <a href="mailto:support@adhd.lk">support@adhd.lk</a>.</strong> Reviewers are credited by name on the pages they check, unless they prefer otherwise.</p>',
    ],
    [
        'h' => 'What we never do',
        'b' => '<ul>
                <li>Diagnose anyone, or tell a reader they have ADHD.</li>
                <li>Give advice on medication, dosages or changes to treatment.</li>
                <li>Recommend specific private practitioners or clinics.</li>
                <li>Publish sponsored content, affiliate links or paid reviews.</li>
                <li>Promise cures, or claim ADHD can be "overcome" or "fixed".</li>
                </ul>',
    ],
    [
        'h' => 'Accuracy and corrections',
        'b' => '<p>If anything here is wrong, outdated or misleading, tell us: <a href="mailto:support@adhd.lk">support@adhd.lk</a> or the <a href="/contact">contact form</a>, choosing "Correction or error report".</p>
                <p>We aim to respond within a few working days. Factual errors are corrected as quickly as we can manage; where a correction changes the meaning of an article, the updated date on the page reflects it. Corrections from clinicians and public institutions are prioritised.</p>',
    ],
    [
        'h' => 'Language versions',
        'b' => '<p>Articles in each language are written for readers of that language rather than translated word for word, because a literal translation of ADHD material reads badly and often misleads.</p>
                <p>One important limitation: the Sinhala and Tamil versions of the ASRS self-test questions are <strong>unofficial translations</strong>, not the WHO\'s own validated versions, and the assessment page says so where the test is taken.</p>',
    ],
    [
        'h' => 'Independence and funding',
        'b' => '<p>ADHD.lk carries no advertising and no sponsorship, sells no products, and takes no money from pharmaceutical companies, clinics or app makers. Running costs are paid by its owner. Nobody can pay to appear on this site, be linked from it, or be listed in the help directory.</p>',
    ],
];

require_once "includes/header.php";
?>

<main class="main-content container" style="max-width: 780px;">
    <div style="width: 100%;">
        <h1>Editorial policy</h1>
        <p style="color: var(--text-secondary); font-size: 1.05rem; margin-bottom: 0.5rem;">
            How ADHD.lk decides what to publish, where the information comes from, and what to do if we get something wrong.
        </p>
        <p style="color: var(--text-faint); font-size: 0.9rem; margin-bottom: 2rem;">
            <?php echo t('legal_updated'); ?>: 18 September 2026
        </p>

        <?php foreach ($sections as $s): ?>
            <section class="glass-card" style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; margin-bottom: 0.75rem;"><?php echo $s['h']; ?></h2>
                <div style="color: var(--text-secondary); font-size: 0.97rem; line-height: 1.75;"><?php echo $s['b']; ?></div>
            </section>
        <?php endforeach; ?>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="/about" class="btn btn-secondary">About ADHD.lk</a>
            <a href="/contact" class="btn btn-secondary">Report an error</a>
            <a href="/privacy" class="btn btn-secondary"><?php echo t('footer_privacy'); ?></a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
