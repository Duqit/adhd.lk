<?php
// print.php — print-ready A4 leaflet and poster for clinics, schools and waiting rooms.
// Standalone (no site nav/footer) so it prints cleanly. /print?doc=poster&lang=si
require_once "includes/i18n.php";

$doc = ($_GET['doc'] ?? 'leaflet') === 'poster' ? 'poster' : 'leaflet';
$lang = current_lang();

// Short strings kept here rather than in lang/*.php: they are specific to the printed
// sheet, and the phrasing matches the published Sinhala/Tamil articles.
$S = [
    'en' => [
        'tag' => 'Free tools and guides for ADHD brains',
        'what_h' => 'What is ADHD?',
        'what' => [
            'A difference in how the brain manages attention, time and impulses — not laziness, and not a lack of intelligence.',
            'It often shows up as trouble starting tasks, losing track of time, forgetting instructions, and strong emotional reactions.',
            'It can be assessed and managed. Support exists in Sri Lanka, free of charge.',
        ],
        'tools_h' => 'Free to use — no account, no cost, no ads',
        'test_h' => 'Free self-check',
        'test' => 'The WHO ASRS v1.1 questionnaire — 18 questions, about 5 minutes. Your answers stay on your own device. It is a screening tool, not a diagnosis.',
        'help_h' => 'Where to get help in Sri Lanka',
        'help' => [
            '<strong>1926</strong> — National Mental Health Helpline (free, 24 hours)',
            '<strong>1333</strong> — CCCline, emotional support',
            '<strong>1990</strong> — Ambulance (emergency)',
            'Every district general and teaching hospital has a mental health clinic. Care there is free.',
        ],
        'scan' => 'Scan to open',
        'poster_h' => 'Struggling to focus, start things, or keep track of time?',
        'poster_sub' => 'Free ADHD tools, guides and a private self-check.',
        'print_btn' => 'Print / Save as PDF',
        'note' => 'Not medical advice. A self-test is not a diagnosis — speak to a doctor.',
    ],
    'si' => [
        'tag' => 'ADHD අය සඳහා නොමිලේ මෙවලම් සහ මඟපෙන්වීම්',
        'what_h' => 'ADHD කියන්නේ මොකක්ද?',
        'what' => [
            'මොළයෙන් අවධානය, කාලය සහ ආවේග පාලනය කරන විදිහේ වෙනසක් — මේක කම්මැලිකමක් හෝ බුද්ධියේ අඩුවක් නෙවෙයි.',
            'ගොඩක් වෙලාවට මේ තත්ත්වය පෙන්නුම් කරන්නේ: වැඩක් පටන් ගන්න තියෙන අමාරුව, කාලය ගතවෙනවා නොතේරීම, කියන දේවල් අමතක වීම සහ ඉක්මනින් හැඟීම්බර වීම විදිහටයි.',
            'මේ තත්ත්වය හඳුනාගෙන පාලනය කරන්න පුළුවන්. ඒ සඳහා ලංකාව තුළ නොමිලේම උදව් ලබාගන්න පුළුවන්.',
        ],
        'tools_h' => 'නොමිලේ පාවිච්චි කරන්න — එකවුන්ට් ඕනේ නෑ, ගාස්තු නෑ, දැන්වීම් නෑ',
        'test_h' => 'නොමිලේ කළ හැකි ස්වයං පරීක්ෂණයක්',
        'test' => 'ලෝක සෞඛ්‍ය සංවිධානයේ ASRS v1.1 ප්‍රශ්නාවලිය — ප්‍රශ්න 18යි, යන්නේ විනාඩි 5ක් විතරයි. ඔබේ පිළිතුරු ඔබේ ෆෝන් එකෙන් හෝ පරිගණකයෙන් පිටට යන්නේ නෑ. මේක මූලික පරීක්ෂණයක් මිසක්, වෛද්‍ය නිගමනයක් නෙවෙයි.',
        'help_h' => 'ලංකාව ඇතුළේ උදව් ලබාගන්න පුළුවන් තැන්',
        'help' => [
            '<strong>1926</strong> — ජාතික මානසික සෞඛ්‍ය උපකාරක සේවාව (නොමිලේ, පැය 24 පුරාම)',
            '<strong>1333</strong> — CCCline, මානසික සහනය සඳහා',
            '<strong>1990</strong> — ගිලන් රථ සේවය (හදිසි අවස්ථාවකදී)',
            'සෑම දිස්ත්‍රික් මහ රෝහලකම සහ ශික්ෂණ රෝහලකම මානසික සෞඛ්‍ය සායනයක් තියෙනවා. එතනින් නොමිලේම ප්‍රතිකාර ලබාගන්න පුළුවන්.',
        ],
        'scan' => 'ස්කෑන් කරන්න',
        'poster_h' => 'වැඩකට අවධානය දෙන්න, වැඩක් පටන්ගන්න, එහෙමත් නැත්නම් කාලය ගැන අවධානයෙන් ඉන්න එක ඔබට අමාරුද?',
        'poster_sub' => 'නොමිලේ භාවිතා කළ හැකි ADHD මෙවලම්, මඟපෙන්වීම් සහ පෞද්ගලිකව කරබලන්න පුළුවන් ස්වයං පරීක්ෂණයක්.',
        'print_btn' => 'Print කරන්න / PDF එකක් විදිහට Save කරන්න',
        'note' => 'මේක වෛද්‍ය උපදෙසක් නෙවෙයි. ස්වයං පරීක්ෂණයක් කළාට ඒක රෝග විනිශ්චයක් වෙන්නේ නෑ — ඒ සඳහා වෛද්‍යවරයෙක් හමුවෙන්න.',
    ],
    'ta' => [
        'tag' => 'ADHD மூளைகளுக்கான இலவச கருவிகளும் வழிகாட்டிகளும்',
        'what_h' => 'ADHD என்றால் என்ன?',
        'what' => [
            'மூளை கவனம், நேரம், உந்துதல்களை நிர்வகிக்கும் விதத்தில் உள்ள வேறுபாடு — சோம்பல் அல்ல, அறிவுக் குறைவும் அல்ல.',
            'பொதுவாகத் தெரிவது: வேலையைத் தொடங்குவதில் சிரமம், நேரம் தெரியாமல் போவது, அறிவுறுத்தல்களை மறப்பது, தீவிர உணர்ச்சி எதிர்வினைகள்.',
            'இதை மதிப்பிடவும் நிர்வகிக்கவும் முடியும். இலங்கையில் இலவச ஆதரவு உள்ளது.',
        ],
        'tools_h' => 'இலவசமாகப் பயன்படுத்தலாம் — கணக்கு இல்லை, கட்டணம் இல்லை, விளம்பரம் இல்லை',
        'test_h' => 'இலவச சுய பரிசோதனை',
        'test' => 'WHO இன் ASRS v1.1 கேள்வித்தாள் — 18 கேள்விகள், சுமார் 5 நிமிடங்கள். உங்கள் பதில்கள் உங்கள் சாதனத்திலேயே இருக்கும். இது ஒரு பரிசோதனைக் கருவி, நோயறிதல் அல்ல.',
        'help_h' => 'இலங்கையில் உதவி எங்கே பெறுவது',
        'help' => [
            '<strong>1926</strong> — தேசிய மனநல உதவி எண் (இலவசம், 24 மணி நேரம்)',
            '<strong>1333</strong> — CCCline, உணர்ச்சி ஆதரவு',
            '<strong>1990</strong> — ஆம்புலன்ஸ் (அவசரம்)',
            'ஒவ்வொரு மாவட்டப் பொது மற்றும் போதனா மருத்துவமனையிலும் மனநல கிளினிக் உள்ளது. அங்கு சிகிச்சை இலவசம்.',
        ],
        'scan' => 'திறக்க ஸ்கேன் செய்யுங்கள்',
        'poster_h' => 'கவனம் செலுத்த, வேலையைத் தொடங்க, நேரத்தைக் கையாள சிரமமா?',
        'poster_sub' => 'இலவச ADHD கருவிகள், வழிகாட்டிகள், தனிப்பட்ட சுய பரிசோதனை.',
        'print_btn' => 'அச்சிடு / PDF ஆக சேமி',
        'note' => 'இது மருத்துவ ஆலோசனை அல்ல. சுய பரிசோதனை நோயறிதல் அல்ல — மருத்துவரை அணுகுங்கள்.',
    ],
];
$x = $S[$lang] ?? $S['en'];

// Tool names/descriptions reuse the strings already published on the site.
$tools = [
    ['⏳', t('home_timer_t'), t('home_timer_d')],
    ['📌', t('home_planner_t'), t('home_planner_d')],
    ['🔄', t('home_routines_t'), t('home_routines_d')],
    ['🔥', t('home_habits_t'), t('home_habits_d')],
    ['📔', t('home_journal_t'), t('home_journal_d')],
    ['🌬️', t('home_calm_t'), t('home_calm_d')],
];
$site_url = 'https://adhd.lk' . ($lang !== 'en' ? '/?lang=' . $lang : '');
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, follow">
<title><?php echo $doc === 'poster' ? 'ADHD.lk poster (A4)' : 'ADHD.lk leaflet (A4)'; ?></title>
<style>
    @page { size: A4; margin: 12mm; }
    :root { --fs: 1; }   /* shrunk by fit() below when the text does not fit one page */
    * { box-sizing: border-box; }
    body { margin: 0; background: #f1f5f9; color: #111;
           font-family: "Segoe UI", Roboto, "Noto Sans Sinhala", "Noto Sans Tamil", Arial, sans-serif; }
    .bar { background: #111827; color: #fff; padding: 12px 16px; text-align: center; font-size: 14px; }
    .bar button { background: #6366f1; color: #fff; border: 0; padding: 9px 20px; border-radius: 8px;
                  font-size: 14px; font-weight: 600; cursor: pointer; margin-left: 10px; }
    .bar a { color: #a5b4fc; }
    .sheet { width: 210mm; min-height: 297mm; margin: 18px auto; background: #fff; padding: 16mm 14mm;
             box-shadow: 0 6px 24px rgba(0,0,0,.18); }
    h1 { font-size: calc(26pt * var(--fs)); margin: 0 0 4px; line-height: 1.15; }
    .tagline { color: #4b5563; font-size: calc(12pt * var(--fs)); margin: 0 0 18px; }
    h2 { font-size: calc(13pt * var(--fs)); margin: calc(18px * var(--fs)) 0 calc(8px * var(--fs)); color: #4338ca; border-bottom: 2px solid #e0e7ff; padding-bottom: 4px; }
    p, li { font-size: calc(10.5pt * var(--fs)); line-height: 1.5; }
    ul { margin: calc(6px * var(--fs)) 0; padding-left: 18px; }
    .tools { display: grid; grid-template-columns: 1fr 1fr; gap: calc(8px * var(--fs)) 16px; margin-top: 6px; }
    .tool { font-size: calc(10pt * var(--fs)); }
    .tool b { display: block; font-size: calc(10.5pt * var(--fs)); }
    .tool span { color: #4b5563; }
    .callout { background: #eef2ff; border-left: 4px solid #6366f1; padding: calc(10px * var(--fs)) 14px; margin: calc(10px * var(--fs)) 0; }
    .help { background: #fff7ed; border-left: 4px solid #f59e0b; padding: calc(10px * var(--fs)) 14px; }
    .foot { margin-top: calc(20px * var(--fs)); border-top: 1px solid #e5e7eb; padding-top: 12px;
            display: flex; gap: 16px; align-items: center; justify-content: space-between; }
    .url { font-size: calc(17pt * var(--fs)); font-weight: 800; color: #4338ca; }
    .note { font-size: calc(8.5pt * var(--fs)); color: #6b7280; margin-top: 6px; }
    #qr { width: 116px; height: 116px; }
    .qrwrap { text-align: center; font-size: calc(8.5pt * var(--fs)); color: #6b7280; }
    /* Poster */
    .poster { display: flex; flex-direction: column; justify-content: center; text-align: center; min-height: 265mm; }
    .poster h1 { font-size: 40pt; line-height: 1.12; margin-bottom: 14px; }
    .poster .sub { font-size: 17pt; color: #374151; margin-bottom: 26px; }
    .poster #qr { width: 240px; height: 240px; margin: 0 auto; }
    .poster .url { font-size: 30pt; margin-top: 18px; }
    .poster .helpline { font-size: 12pt; margin-top: 26px; color: #374151; }
    @media print {
        body { background: #fff; }
        .bar { display: none; }
        .sheet { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
    }
</style>
</head>
<body>
<div class="bar">
    <?php echo $doc === 'poster' ? 'A4 poster' : 'A4 leaflet'; ?> —
    <a href="/print?doc=leaflet&lang=<?php echo $lang; ?>">leaflet</a> ·
    <a href="/print?doc=poster&lang=<?php echo $lang; ?>">poster</a> ·
    <a href="/print?doc=<?php echo $doc; ?>&lang=en">EN</a> ·
    <a href="/print?doc=<?php echo $doc; ?>&lang=si">සිං</a> ·
    <a href="/print?doc=<?php echo $doc; ?>&lang=ta">தமிழ்</a>
    <button onclick="window.print()"><?php echo htmlspecialchars($x['print_btn']); ?></button>
</div>

<div class="sheet">
<?php if ($doc === 'poster'): ?>
    <div class="poster">
        <h1><?php echo htmlspecialchars($x['poster_h']); ?></h1>
        <p class="sub"><?php echo htmlspecialchars($x['poster_sub']); ?></p>
        <div id="qr"></div>
        <div class="url">adhd.lk</div>
        <p class="helpline"><?php echo $x['help'][0]; ?></p>
        <p class="note"><?php echo htmlspecialchars($x['note']); ?></p>
    </div>
<?php else: ?>
    <div id="content">
    <h1>ADHD.lk</h1>
    <p class="tagline"><?php echo htmlspecialchars($x['tag']); ?></p>

    <h2><?php echo htmlspecialchars($x['what_h']); ?></h2>
    <ul><?php foreach ($x['what'] as $w): ?><li><?php echo htmlspecialchars($w); ?></li><?php endforeach; ?></ul>

    <h2><?php echo htmlspecialchars($x['tools_h']); ?></h2>
    <div class="tools">
        <?php foreach ($tools as $t): ?>
            <div class="tool"><b><?php echo $t[0] . ' ' . htmlspecialchars($t[1]); ?></b><span><?php echo htmlspecialchars($t[2]); ?></span></div>
        <?php endforeach; ?>
    </div>

    <div class="callout">
        <h2 style="margin-top:0;border:0;padding:0;"><?php echo htmlspecialchars($x['test_h']); ?></h2>
        <p style="margin:0;"><?php echo htmlspecialchars($x['test']); ?></p>
    </div>

    <h2><?php echo htmlspecialchars($x['help_h']); ?></h2>
    <div class="help">
        <ul style="margin:0;"><?php foreach ($x['help'] as $h): ?><li><?php echo $h; ?></li><?php endforeach; ?></ul>
    </div>

    <div class="foot">
        <div>
            <div class="url">adhd.lk</div>
            <p class="note" style="margin-top:8px;"><?php echo htmlspecialchars($x['note']); ?></p>
        </div>
        <div class="qrwrap">
            <div id="qr"></div>
            <?php echo htmlspecialchars($x['scan']); ?>
        </div>
    </div>
    </div><!-- /#content -->
<?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qr'), {
        text: <?php echo json_encode($site_url); ?>,
        width: <?php echo $doc === 'poster' ? 240 : 116; ?>,
        height: <?php echo $doc === 'poster' ? 240 : 116; ?>,
        correctLevel: QRCode.CorrectLevel.M
    });

    // Shrink the leaflet until it fits one A4 page. Sinhala and Tamil run longer
    // than English, so a fixed size would overflow onto a second sheet.
    (function fit() {
        const content = document.getElementById('content');
        if (!content) return;                        // poster is short by design
        const probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;visibility:hidden;height:273mm';  // A4 minus 12mm margins
        document.body.appendChild(probe);
        // 4% slack: printer margins and font metrics differ slightly from screen,
        // and landing exactly on the page boundary spills onto a second sheet.
        const maxH = probe.offsetHeight * 0.96;
        probe.remove();
        let fs = 1;
        while (content.offsetHeight > maxH && fs > 0.62) {
            fs -= 0.02;
            document.documentElement.style.setProperty('--fs', fs.toFixed(2));
        }
        document.documentElement.dataset.fit = fs.toFixed(2);
    })();
</script>
</body>
</html>
