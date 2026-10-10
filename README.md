# ADHD.lk

**Free, practical tools for ADHD brains, and plain-language guides in English, Sinhala and Tamil.**
No ads. No paywall. No account needed.

🌐 **Live site:** [adhd.lk](https://adhd.lk)

ADHD.lk began in Sri Lanka, where almost nothing about ADHD existed in Sinhala or Tamil. It is
built by people who live with ADHD, for anyone who does. The source is public so you can see
exactly how it works and what it does with your data, and help make it better.

> **Not medical advice.** ADHD.lk offers self-help tools and information. It is not a diagnosis
> or a substitute for a qualified clinician.

---

## What's inside

| Tool | What it does |
|---|---|
| ⏳ **Visual focus timer** | Pomodoro timer with a full-screen focus mode and layered ambient sound (brown noise, rain, ocean) |
| 📌 **Today planner** | Pick your top 3 priorities, check in on your mood, earn XP for finishing things |
| 🔄 **Routine builder** | Build step-by-step routines and run them full-screen, one step at a time |
| 🔥 **Habit streaks** | Tiny daily habits with a visible streak |
| 📔 **Journal** | A fresh prompt every day, plus a quick "brain dump" inbox |
| 🌬️ **Calm corner** | 4-7-8 and box breathing, and the 5-4-3-2-1 grounding exercise |
| 📋 **ADHD self-test** | The WHO ASRS v1.1 screener, scored entirely in the browser |
| 📚 **Articles** | Plain-language guides, including where to get help in Sri Lanka |

Every tool works **without an account**: data stays in the browser's `localStorage`. Creating a
free account syncs it across devices, and anything done as a guest is copied into the new
account once.

## Privacy principles

- **Guest data never leaves your device.** We cannot read it.
- **The self-test is never uploaded.** Answers and scores stay in the page.
- **Account holders can export everything** as JSON or **delete their account** from Settings.
- **We use Google Analytics** to see which tools people use. It is skipped when the browser sends
  Do Not Track or Global Privacy Control. Details are in the
  [privacy policy](https://adhd.lk/privacy).

## Tech stack

Deliberately simple so anyone can run and contribute to it:

- **PHP 8** (no framework) with **MySQL / MariaDB** through PDO prepared statements
- **Vanilla JavaScript and CSS**, with no build step and no npm
- **Apache** with `.htaccess` clean URLs, behind **Cloudflare** (HTML edge caching for anonymous visitors)
- An installable **PWA** with an offline page
- Mail through the Cloudflare Email Sending API (optional for local development)

```
public_html/          everything served by the web server
├── *.php             one file per page (timer.php, dashboard.php, article.php …)
├── includes/         shared code: boot (security headers, sessions), header/footer,
│                     i18n, CSRF, rate limiting, mailer, config (blocked from the web)
├── lang/             en.php, si.php, ta.php — every user-facing string
├── js/               tool logic; *-guest.js = localStorage mode, store.js = sync
├── css/style.css     the whole design system (dark + light themes)
└── api/import.php    one-time import of guest data into a new account
database/schema.sql   table structure (no data)
dev/router.php        clean-URL router for PHP's built-in server
```

## Run it locally

You need PHP 8 with `pdo_mysql` and `curl`, and MySQL or MariaDB.
[XAMPP](https://www.apachefriends.org/) gives you all three on Windows and macOS.

```bash
git clone https://github.com/duqit/adhd.lk.git
cd adhd.lk

# 1. Database
mysql -u root -e "CREATE DATABASE adhd CHARACTER SET utf8mb4"
mysql -u root adhd < database/schema.sql

# 2. Config: copy the sample and fill in your local database details.
#    Leave RECAPTCHA_SECRET empty locally; login/register then skip reCAPTCHA on localhost.
cp public_html/includes/config.sample.php public_html/includes/config.php

# 3. Run
php -S localhost:8000 -t public_html dev/router.php
```

Open <http://localhost:8000>. To use the admin pages (`/admin`, `/admin_articles`), register a
user and promote it:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your-name';
```

Email (password reset, contact form) is optional. Copy `includes/mail_config.sample.php` to
`includes/mail_config.php` if you want to test it.

## Contributing

Contributions are very welcome, especially **Sinhala and Tamil translations**, accessibility
fixes, and corrections to content. Please read [CONTRIBUTING.md](CONTRIBUTING.md) first, and
follow the [Code of Conduct](CODE_OF_CONDUCT.md).

Found a security issue? Please **don't** open a public issue. See [SECURITY.md](SECURITY.md).

## Licence

- **Code:** [GNU AGPL-3.0](LICENSE). If you run a modified version as a website, share your changes.
- **Written content** (interface text, translations, page copy, articles): [CC BY-SA 4.0](LICENSE-CONTENT.md).

## Credits

Built and maintained by **[Duqit Labs](https://duqit.com)**, created by Nishnath Thaweesha.
Questions and corrections: [support@adhd.lk](mailto:support@adhd.lk) or the
[contact page](https://adhd.lk/contact).
