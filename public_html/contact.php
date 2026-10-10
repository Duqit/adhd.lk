<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once "includes/i18n.php";
require_once "includes/csrf.php";
require_once "includes/mailer.php";

$page_title = 'Contact ADHD.lk — Questions, Corrections, Organisations';
$page_desc = 'Contact ADHD.lk: report an error in an article, ask a question, request printed materials for a clinic or school, or suggest a service for the help directory.';
$page_canonical = 'https://adhd.lk/contact';
// English-only for now: no ?lang= duplicates and no hreflang until it is translated.
$skip_hreflang = true;
$page_lang = 'en';

$TOPICS = [
    'general' => 'General question',
    'correction' => 'Correction or error report',
    'clinician' => 'Clinician / organisation',
    'materials' => 'Request printed materials',
    'directory' => 'Add or update a service in the help directory',
    'other' => 'Something else',
];

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $topic = isset($TOPICS[$_POST['topic'] ?? '']) ? $_POST['topic'] : 'general';
    $msg = trim($_POST['message'] ?? '');
    $trap = trim($_POST['website'] ?? ''); // honeypot: real people never see this field

    $_SESSION['contact_sent'] = array_values(array_filter(
        $_SESSION['contact_sent'] ?? [],
        function ($t) { return $t > time() - 3600; }
    ));

    if ($trap !== '') {
        $sent = true; // silently accept spam bots
    } elseif ($name === '' || $msg === '') {
        $error = 'Please add your name and a message.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter an email address we can reply to.';
    } elseif (mb_strlen($msg) > 5000) {
        $error = 'That message is too long — please keep it under 5,000 characters.';
    } elseif (count($_SESSION['contact_sent']) >= 3) {
        $error = 'You have sent several messages already. Please wait an hour, or email support@adhd.lk directly.';
    } else {
        $to = defined('ADHD_CONTACT_TO') ? ADHD_CONTACT_TO : 'support@adhd.lk';
        $body = '<p><strong>Topic:</strong> ' . htmlspecialchars($TOPICS[$topic]) . '</p>'
              . '<p><strong>From:</strong> ' . htmlspecialchars($name) . ' &lt;' . htmlspecialchars($email) . '&gt;</p>'
              . '<p><strong>Message:</strong></p><p>' . nl2br(htmlspecialchars($msg)) . '</p>';
        $html = adhd_mail_template('New message from the ADHD.lk contact form', $body);

        if (adhd_send_mail($to, '[ADHD.lk] ' . $TOPICS[$topic] . ' — ' . mb_substr($name, 0, 60), $html, $email)) {
            $_SESSION['contact_sent'][] = time();
            $sent = true;
        } else {
            $error = 'Sorry — the message could not be sent just now. Please email support@adhd.lk directly.';
        }
    }
}

require_once "includes/header.php";
?>

<main class="main-content container" style="max-width: 780px;">
    <div style="width: 100%;">
        <h1>Contact</h1>
        <p style="color: var(--text-secondary); font-size: 1.05rem; margin-bottom: 2rem;">
            ADHD.lk is run by one person. I read everything and reply to most messages within a few working days.
        </p>

        <?php if ($sent): ?>
            <div class="message" style="margin-bottom: 1.5rem;">Thank you — your message has been sent. If it needs a reply, you will hear back at the address you gave.</div>
        <?php elseif ($error): ?>
            <div class="error" style="margin-bottom: 1.5rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="glass-card" style="margin-bottom: 1.25rem; border-left: 3px solid var(--color-warn);">
            <h2 style="font-size: 1.05rem; margin-bottom: 0.5rem;">Please read first</h2>
            <div style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7;">
                <p>I cannot give medical advice, interpret your test result, or tell you whether you have ADHD — I am not a clinician. For those questions please see a family doctor, psychiatrist or psychologist. If you are in Sri Lanka, the <a href="/article/65-adhd-help-sri-lanka-directory">help directory</a> shows where to go.</p>
                <p style="margin-bottom: 0;"><strong>If you are in crisis:</strong> call your local emergency number or a crisis line in your country (<a href="https://findahelpline.com" target="_blank" rel="noopener">findahelpline.com</a>). In Sri Lanka: <strong>1926</strong> (mental health helpline, free, 24 hours), <strong>1333</strong> (CCCline), <strong>1990</strong> (ambulance). This form is not monitored around the clock.</p>
            </div>
        </section>

        <?php if (!adhd_mail_configured()): ?>
            <section class="glass-card" style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.05rem; margin-bottom: 0.5rem;">Email us</h2>
                <p style="color: var(--text-secondary); font-size: 0.97rem; line-height: 1.75;">The contact form is not available at the moment. Please write to <a href="mailto:support@adhd.lk"><strong>support@adhd.lk</strong></a> and we will reply from there.</p>
            </section>
        <?php elseif (!$sent): ?>
        <section class="glass-card" style="margin-bottom: 1.25rem;">
            <form method="POST" action="/contact">
                <?php echo csrf_field(); ?>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Your name *</label>
                        <input type="text" name="name" class="form-control" required maxlength="100" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Your email *</label>
                        <input type="email" name="email" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>What is this about?</label>
                    <select name="topic" class="form-control">
                        <?php foreach ($TOPICS as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo (($_POST['topic'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" rows="7" class="form-control" required maxlength="5000"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>

                <!-- honeypot -->
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <label>Website</label>
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary">Send message</button>
                <p style="color: var(--text-faint); font-size: 0.85rem; margin-top: 0.75rem;">
                    Your message and email address are used only to reply to you. See the <a href="/privacy"><?php echo t('footer_privacy'); ?></a>.
                </p>
            </form>
        </section>
        <?php endif; ?>

        <section class="glass-card">
            <h2 style="font-size: 1.05rem; margin-bottom: 0.75rem;">Other ways to reach us</h2>
            <div style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.8;">
                <p><strong>Email:</strong> <a href="mailto:support@adhd.lk">support@adhd.lk</a></p>
                <p><strong>Clinicians and organisations:</strong> printed leaflets and posters for clinics, schools and waiting rooms are free — just ask. Corrections from clinicians are prioritised, and we are looking for professionals willing to review our material. See the <a href="/editorial-policy">editorial policy</a>.</p>
                <p style="margin-bottom: 0;"><strong>Who runs this site:</strong> see <a href="/about">About ADHD.lk</a>.</p>
            </div>
        </section>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
