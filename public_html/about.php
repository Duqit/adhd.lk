<?php
require_once "includes/i18n.php";
$page_title = 'About ADHD.lk — Who Builds It and Why | ADHD.lk';
$page_desc = 'ADHD.lk is a free, independent ADHD toolkit built by Nishnath Thaweesha, a developer living with ADHD. No ads, no paywall, no account required. Here is who runs it and how it works.';
$page_canonical = 'https://adhd.lk/about';
// English-only for now: no ?lang= duplicates and no hreflang until it is translated.
$skip_hreflang = true;
$page_lang = 'en';

// English copy lives here for now; lift into lang/*.php when the translated versions are ready.
$sections = [
    [
        'h' => 'Why this site exists',
        'b' => '<p>ADHD.lk started because the tools I needed did not exist in a form I could actually use. Most ADHD apps hide the useful parts behind a subscription, ask you to create an account before you can try anything, and are built as though remembering to open an app were the easy part.</p>
                <p>I have ADHD myself, so I built the opposite: tools that work the moment the page loads. No sign-up, no ads, no cost, nothing to cancel. If you never make an account, everything still works — your data simply stays in your own browser.</p>
                <p>It is written for anyone with ADHD, anywhere. The project started in Sri Lanka, where almost nothing about ADHD existed in Sinhala or Tamil, so those versions are here too, and more languages are planned.</p>',
    ],
    [
        'h' => 'Who is behind it',
        'b' => '<p>ADHD.lk is built and maintained by <strong>Nishnath Thaweesha</strong>, a software developer living with ADHD.</p>
                <p>I am not a doctor, a psychologist, or a clinician of any kind, and this site never pretends otherwise. What I can do is build tools that fit the way an ADHD brain actually works, and explain what the published research says in plain language, with the sources named so you can check them yourself.</p>
                <p>Questions, corrections and suggestions are genuinely welcome: <a href="mailto:support@adhd.lk">support@adhd.lk</a> or the <a href="/contact">contact page</a>.</p>',
    ],
    [
        'h' => 'What ADHD.lk is',
        'b' => '<ul>
                <li><strong>Free tools that work without an account</strong> — a visual <a href="/timer">focus timer</a>, a three-task <a href="/dashboard">daily planner</a>, step-by-step <a href="/routines">routines</a>, a forgiving <a href="/habits">habit tracker</a> whose streaks pause instead of resetting, a <a href="/journal">journal</a>, and a <a href="/breathe">calm corner</a> for overwhelm.</li>
                <li><strong>The WHO ASRS v1.1 self-screening test</strong>, scored to specification and run entirely in your browser — your answers never reach our server. <a href="/assessment">Take it here</a>.</li>
                <li><strong>Plain-language guides</strong> on how ADHD works day to day, based on published research, with the sources linked.</li>
                <li><strong>Guides in Sinhala and Tamil</strong>, plus a <a href="/article/65-adhd-help-sri-lanka-directory">directory of where to get ADHD help in Sri Lanka</a> for readers there.</li>
                <li><strong>No advertising, no trackers, no data selling, nothing behind a paywall.</strong></li>
                </ul>',
    ],
    [
        'h' => 'What ADHD.lk is not',
        'b' => '<p>This is an information and self-help site, not a medical service.</p>
                <ul>
                <li>Nothing here is medical advice, a diagnosis, or a substitute for seeing a qualified professional.</li>
                <li>The self-test is a <em>screening questionnaire</em>. A positive result means "this is worth discussing with a professional", never "you have ADHD".</li>
                <li>We do not give advice about medication, dosages or treatment decisions. Those belong with your doctor.</li>
                </ul>
                <p><strong>If you are struggling right now</strong>, call your local emergency number, or find a crisis line for your country at <a href="https://findahelpline.com" target="_blank" rel="noopener">findahelpline.com</a>. In Sri Lanka, the national mental health helpline is <strong>1926</strong> (free, 24 hours), CCCline is <strong>1333</strong>, and <strong>1990</strong> is the ambulance service.</p>',
    ],
    [
        'h' => 'How the content is written',
        'b' => '<p>Every guide is based on published sources — the World Health Organization, the CDC, the US National Institute of Mental Health, and peer-reviewed research — and those sources are named in the articles rather than implied.</p>
                <p>Our content is <strong>not reviewed by a clinician</strong>, and we say so plainly on every page rather than burying it. The full explanation of how topics are chosen, which sources are used, and how to report an error is on the <a href="/editorial-policy">editorial policy page</a>.</p>',
    ],
    [
        'h' => 'Your privacy, in one line',
        'b' => '<p>If you use the tools as a guest, your data stays in your own browser and never reaches us. If you create an account, we store what you save so you can reach it from another device — and we do not sell or share it with anyone. The <a href="/privacy">privacy policy</a> has the detail, and you can <a href="/data_deletion_instructions">delete everything</a> whenever you want.</p>',
    ],
    [
        'h' => 'Open source',
        'b' => '<p>The complete source code of ADHD.lk is public on <a href="https://github.com/duqit/adhd.lk" rel="noopener">GitHub</a>, developed by <a href="https://duqit.com" rel="noopener">Duqit Labs</a>. Anyone can check exactly how the tools work and what happens to your data, and anyone can help: fix a bug, improve a Sinhala or Tamil translation, or report something that is wrong.</p>
                <p>The code is free under the GNU AGPL-3.0 licence and the written content under Creative Commons BY-SA 4.0, so it can be reused and translated as long as it stays open.</p>',
    ],
    [
        'h' => 'For clinicians, schools and organisations',
        'b' => '<p>If you work in mental health, education or public health, you are welcome to use anything here with your patients, students or clients:</p>
                <ul>
                <li>The guides can be printed and handed out freely.</li>
                <li>The self-test is free, private and needs no account — nothing to sign up for, nothing collected.</li>
                <li>The Sri Lanka help directory lists public institutions and services rather than individual practitioners, so it stays accurate.</li>
                </ul>
                <p><strong>If you spot something inaccurate, please tell me and I will fix it.</strong> Corrections from clinicians go to the top of the queue.</p>
                <p><strong>Printable materials are ready to use:</strong> an <a href="/print">A4 leaflet</a> and a <a href="/print?doc=poster">noticeboard poster</a>, both available in Sinhala, Tamil and English — open, choose the language, and print. If you would like your service added to the help directory, or need the materials in another format, email <a href="mailto:support@adhd.lk">support@adhd.lk</a>. There is no charge for any of it.</p>',
    ],
];

require_once "includes/header.php";
?>

<main class="main-content container" style="max-width: 780px;">
    <div style="width: 100%;">
        <h1>About ADHD.lk</h1>
        <p style="color: var(--text-secondary); font-size: 1.05rem; margin-bottom: 2rem;">
            A free, independent set of ADHD tools and guides — built by someone with ADHD, for people with ADHD.
        </p>

        <?php foreach ($sections as $s): ?>
            <section class="glass-card" style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; margin-bottom: 0.75rem;"><?php echo $s['h']; ?></h2>
                <div style="color: var(--text-secondary); font-size: 0.97rem; line-height: 1.75;"><?php echo $s['b']; ?></div>
            </section>
        <?php endforeach; ?>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="/contact" class="btn btn-primary">Contact</a>
            <a href="/editorial-policy" class="btn btn-secondary">Editorial policy</a>
            <a href="/privacy" class="btn btn-secondary"><?php echo t('footer_privacy'); ?></a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
