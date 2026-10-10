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
        const needed = s.level * 100;
        if (s.xp >= needed) {
            s.level++;
            s.xp -= needed;
        }
        this.set(this.KEYS.stats, s);
        this.toast('+' + amount + ' XP');
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
