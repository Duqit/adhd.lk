/* ============================================================
   store.js — localStorage data layer for guest mode + sync.
   Every tool works without an account. When a user signs up,
   importLocalData() pushes their guest data to the server once.
   ============================================================ */

window.Store = window.Store || {
    KEYS: {
        tasks: 'adhd_tasks',        // [{id,title,est,done,date}]
        moods: 'adhd_moods',        // [{score,note,date}]
        journal: 'adhd_journal',    // [{prompt,text,date}]
        routines: 'adhd_routines',  // [{id,name,steps:[{title,mins}]}]
        habits: 'adhd_habits',      // [{id,name,emoji,created}]
        habitLogs: 'adhd_habit_logs', // {habitId:[dates]}
        stats: 'adhd_stats',        // {xp,level}
        dumps: 'brain_dumps',       // [{id,text,date}] (legacy key kept)
        synced: 'adhd_synced'
    },

    get(key, def) {
        try {
            const raw = localStorage.getItem(key);
            return raw === null ? def : JSON.parse(raw);
        } catch (e) { return def; }
    },

    set(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    },

    uid() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    },

    today() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    },

    dateOffset(days) {
        const d = new Date();
        d.setDate(d.getDate() + days);
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    },

    /* --- Gamification (guest) — mirrors server logic --- */
    getStats() {
        return this.get(this.KEYS.stats, { xp: 0, level: 1 });
    },

    addXP(amount) {
        const s = this.getStats();
        s.xp += amount;
        if (s.xp >= s.level * 100) {
            s.xp -= s.level * 100;
            s.level++;
        }
        // Negative amounts come from un-ticking: walk levels back, never below zero.
        while (s.xp < 0 && s.level > 1) {
            s.level--;
            s.xp += s.level * 100;
        }
        if (s.xp < 0) s.xp = 0;
        this.set(this.KEYS.stats, s);
        this.toast((amount > 0 ? '+' : '') + amount + ' XP');
        return s;
    },

    /* --- UI feedback --- */
    toast(msg) {
        let el = document.getElementById('adhd-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'adhd-toast';
            el.className = 'toast';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => el.classList.remove('show'), 2200);
    },

    /* --- Sign-up prompt at moments of progress (guests only) ---
       Shown after a finished focus session, a 3-day habit streak or the third journal
       entry. Gentle by design: each trigger at most once, at least 2 days between
       prompts, none for 14 days after "Not now", and it never takes keyboard focus. */
    promptSignup(trigger, delayMs) {
        if (window.ADHD_USER) return;
        const KEY = 'adhd_signup_prompt';
        const DAY = 864e5, now = Date.now();
        const st = this.get(KEY, {});
        st.shown = st.shown || {};
        if (st.shown[trigger]) return;
        if (st.dismissedAt && now - st.dismissedAt < 14 * DAY) return;
        if (st.lastShownAt && now - st.lastShownAt < 2 * DAY) return;
        if (document.getElementById('signup-prompt')) return;

        const T = (k, fb) => (window.ADHD_I18N && window.ADHD_I18N[k]) || fb;
        const titles = {
            focus: T('prompt_focus_t', 'Focus session done 🎉'),
            streak: T('prompt_streak_t', '3-day streak — nice! 🔥'),
            journal: T('prompt_journal_t', '3 journal entries 📔')
        };

        setTimeout(() => {
            if (document.getElementById('signup-prompt')) return;
            const box = document.createElement('div');
            box.id = 'signup-prompt';
            box.className = 'signup-prompt';
            box.setAttribute('role', 'region');
            box.setAttribute('aria-live', 'polite');
            box.setAttribute('aria-label', titles[trigger] || titles.focus);

            const h = document.createElement('p');
            h.className = 'signup-prompt-title';
            h.textContent = titles[trigger] || titles.focus;
            const p = document.createElement('p');
            p.className = 'signup-prompt-body';
            p.textContent = T('prompt_body', 'Right now your progress is saved only in this browser. A free account keeps it safe if you clear your browser or switch phones, and everything so far moves across automatically.');
            const actions = document.createElement('div');
            actions.className = 'signup-prompt-actions';
            const cta = document.createElement('a');
            cta.className = 'btn btn-primary';
            cta.href = '/register';
            cta.setAttribute('data-cta', 'prompt_' + trigger);
            cta.textContent = T('prompt_cta', 'Create free account');
            const later = document.createElement('button');
            later.type = 'button';
            later.className = 'btn btn-secondary';
            later.textContent = T('prompt_dismiss', 'Not now');
            actions.append(cta, later);
            box.append(h, p, actions);
            document.body.appendChild(box);
            requestAnimationFrame(() => box.classList.add('show'));

            const close = () => {
                const s = this.get(KEY, {});
                s.dismissedAt = Date.now();
                this.set(KEY, s);
                if (window.adhdTrack) adhdTrack('signup_prompt_dismiss', { trigger: trigger });
                box.remove();
                document.removeEventListener('keydown', onKey);
            };
            const onKey = e => { if (e.key === 'Escape') close(); };
            later.addEventListener('click', close);
            document.addEventListener('keydown', onKey);

            st.shown[trigger] = Date.now();
            st.lastShownAt = Date.now();
            this.set(KEY, st);
            if (window.adhdTrack) adhdTrack('signup_prompt_shown', { trigger: trigger });
        }, delayMs || 0);
    },

    /* --- One-time import of guest data after signup/login --- */
    hasLocalData() {
        const K = this.KEYS;
        return [K.tasks, K.moods, K.journal, K.routines, K.habits]
            .some(k => (this.get(k, [])).length > 0);
    },

    importLocalData() {
        if (!window.ADHD_USER) return;
        if (this.get(this.KEYS.synced, false)) return;
        if (!this.hasLocalData()) { this.set(this.KEYS.synced, true); return; }

        const K = this.KEYS;
        const payload = {
            tasks: this.get(K.tasks, []),
            moods: this.get(K.moods, []),
            journal: this.get(K.journal, []),
            routines: this.get(K.routines, []),
            habits: this.get(K.habits, []),
            habitLogs: this.get(K.habitLogs, {})
        };

        // NOTE: must be the extensionless URL — .htaccess 301-redirects *.php,
        // and a 301 converts POST→GET and drops the request body.
        fetch('/api/import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.ok) {
                // Server is now the source of truth; clear guest copies.
                [K.tasks, K.moods, K.journal, K.routines, K.habits, K.habitLogs]
                    .forEach(k => localStorage.removeItem(k));
                this.set(K.synced, true);
                this.toast((window.ADHD_I18N && window.ADHD_I18N.js_synced) || 'Your guest data was saved to your account ✔');
                // Reload so pages render server data.
                setTimeout(() => window.location.reload(), 1200);
            }
        })
        .catch(() => {});
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // If the user just logged in and has guest data, import it once.
    if (window.ADHD_USER) {
        Store.importLocalData();
    } else {
        // New session as guest: allow future import after signup.
        localStorage.removeItem(Store.KEYS.synced);
    }
});
