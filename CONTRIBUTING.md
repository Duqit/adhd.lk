# Contributing to ADHD.lk

Thank you for helping. ADHD.lk is used by people who are often overwhelmed, so every change
should make the site **simpler, calmer, faster or more accurate**, never more complicated.

## Ways to help

- **Translations.** Improve the Sinhala (`public_html/lang/si.php`) or Tamil
  (`public_html/lang/ta.php`) strings, or start a new language.
- **Content corrections.** Something wrong, outdated or unclear on the site? Open a
  "Content correction" issue with a source.
- **Accessibility.** Keyboard navigation, screen readers, contrast, reduced motion, small screens.
- **Bugs and small improvements.** Check the open issues first.
- **Clinical review.** If you are a qualified clinician willing to review articles, email
  [support@adhd.lk](mailto:support@adhd.lk). Reviewers who read Sinhala or Tamil are especially welcome.

For anything large (a new tool, a redesign, a new dependency) please **open an issue to discuss it
before writing code**.

## Getting set up

Follow **Run it locally** in the [README](README.md). It takes about five minutes with XAMPP or
PHP's built-in server.

## Code guidelines

The project is plain PHP, vanilla JavaScript and one CSS file, on purpose. Please keep it that way:

- **No frameworks, build steps or package managers.** Anyone should be able to edit a file and refresh.
- **Every tool must keep working without an account.** Guest mode uses `localStorage`
  (`js/*-guest.js`, `js/store.js`); logged-in mode uses the database.
- **Security basics are not optional:**
  - database access only through PDO prepared statements
  - escape output with `htmlspecialchars()`
  - forms that change data must use `csrf_field()` / `csrf_check()` from `includes/csrf.php`
  - pages for logged-in users must check `$_SESSION['user_id']`
- **Sessions.** Public pages call `adhd_session_start()` (see `includes/boot.php`) so anonymous
  visitors get a cacheable page. Only call `session_start()` directly on pages that really need a
  session (login, forms with CSRF tokens).
- **Text.** Every user-facing string goes in `public_html/lang/en.php` with a matching key in
  `si.php` and `ta.php`. Use `t('key')` in templates. If you can't translate a string, add the
  English text and mention it in your pull request.
- **Match the surrounding code**: naming, comment style, indentation.
- **Never commit secrets.** `includes/config.php` and `includes/mail_config.php` are gitignored;
  only edit the `*.sample.php` versions.

## Content guidelines

These follow the site's [editorial policy](https://adhd.lk/editorial-policy):

- **Plain language.** If a reader needs a dictionary, rewrite it.
- **Sources you can check.** Link claims to WHO, NICE, CDC/NIMH, peer-reviewed research or
  established ADHD organisations.
- **Be honest about limits.** Say where evidence is thin or a term is community language rather
  than clinical.
- **Never** diagnose, give medication or dosage advice, recommend specific private practitioners,
  promise cures, or add sponsored or affiliate content.
- **Write for the reader of each language.** Sinhala and Tamil text should read naturally,
  not as a word-for-word translation.

## Pull requests

1. Fork the repo and create a branch from `main`.
2. Keep each pull request focused on one change.
3. Test it locally: the pages you touched, in all three languages, logged out **and** logged in,
   on a phone-sized window, in both dark and light themes.
4. Run `php -l` on the PHP files you changed.
5. Describe what changed and why. Add screenshots for visual changes.

By contributing you agree that your code is licensed under the AGPL-3.0 and your written content
under CC BY-SA 4.0 (see [LICENSE-CONTENT.md](LICENSE-CONTENT.md)).
