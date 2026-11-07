## What does this change?

<!-- One or two sentences. Link the issue it fixes, e.g. "Fixes #12". -->

## Why?

## Checklist

- [ ] Tested locally (`php -S localhost:8000 -t public_html dev/router.php`)
- [ ] Checked the pages I touched **logged out and logged in**
- [ ] Checked them in **English, Sinhala and Tamil** (new text goes in all three `lang/*.php` files)
- [ ] Checked on a **phone-sized** window, in **dark and light** themes
- [ ] Forms that change data use `csrf_field()` / `csrf_check()`; output is escaped with `htmlspecialchars()`
- [ ] Changed CSS/JS? I bumped its `?v=` where it is loaded
- [ ] No secrets, passwords or personal data in the diff
- [ ] Content changes follow the [editorial policy](https://adhd.lk/editorial-policy) (sources, no medical advice)

## Screenshots

<!-- For visual changes: before / after. -->
