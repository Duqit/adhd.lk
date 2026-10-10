// i18n lookup with English fallback
var TJ = function (k, fb) { return (window.ADHD_I18N && window.ADHD_I18N[k]) || fb; };

/* dashboard-guest.js — powers the Today planner for guests (localStorage)
   and the Brain Dump inbox for everyone. */

document.addEventListener('DOMContentLoaded', () => {
    const K = Store.KEYS;
    const today = Store.today();
    const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

    /* ================= Brain Dump Inbox (all users) ================= */
    const dumpList = document.getElementById('dump-list');
    const dumpEmpty = document.getElementById('dump-empty');

    function renderDumps() {
        if (!dumpList) return;
        const dumps = Store.get(K.dumps, []).slice().reverse();
        dumpList.innerHTML = '';
        dumpEmpty.classList.toggle('hidden', dumps.length > 0);
        dumps.slice(0, 8).forEach(d => {
            const li = document.createElement('li');
            li.className = 'task-item';
            li.innerHTML = '<span class="task-title" style="font-size:0.92rem;">' + esc(d.text) + '</span>' +
                '<button class="task-del" title="Dismiss">&times;</button>';
            li.querySelector('.task-del').addEventListener('click', () => {
                const all = Store.get(K.dumps, []).filter(x => x.id !== d.id);
                Store.set(K.dumps, all);
                renderDumps();
            });
            dumpList.appendChild(li);
        });
    }
    renderDumps();

    /* ================= Guest-only widgets ================= */
    if (window.ADHD_USER) return;

    /* ---- Stats ---- */
    function renderStats() {
        const s = Store.getStats();
        const needed = s.level * 100;
        const lvl = document.getElementById('g-level');
        const label = document.getElementById('g-xp-label');
        const bar = document.getElementById('g-xp-bar');
        if (lvl) lvl.textContent = s.level;
        if (label) label.textContent = s.xp + ' / ' + needed + ' XP';
        if (bar) bar.style.width = Math.min(100, (s.xp / needed) * 100) + '%';
    }
    renderStats();

    /* ---- Top 3 Tasks ---- */
    const taskList = document.getElementById('g-task-list');
    const taskForm = document.getElementById('g-task-form');

    function getTodayTasks() {
        return Store.get(K.tasks, []).filter(t => t.date === today);
    }

    function renderTasks() {
        if (!taskList) return;
        const tasks = getTodayTasks();
        taskList.innerHTML = '';
        tasks.forEach(t => {
            const li = document.createElement('li');
            li.className = 'task-item' + (t.done ? ' completed' : '');
            li.innerHTML =
                '<input type="checkbox" class="task-checkbox"' + (t.done ? ' checked' : '') + '>' +
                '<span class="task-title">' + esc(t.title) +
                (t.est ? ' <span class="task-badge">' + TJ('js_est', 'Est') + ': ' + parseInt(t.est, 10) + 'm</span>' : '') +
                '</span>' +
                '<button class="task-del" title="Remove">&times;</button>';

            li.querySelector('.task-checkbox').addEventListener('change', () => {
                const all = Store.get(K.tasks, []);
                const item = all.find(x => x.id === t.id);
                if (item) {
                    item.done = !item.done;
                    Store.set(K.tasks, all);
                    if (item.done) Store.addXP(10);
                    renderTasks(); renderStats();
                }
            });
            li.querySelector('.task-del').addEventListener('click', () => {
                Store.set(K.tasks, Store.get(K.tasks, []).filter(x => x.id !== t.id));
                renderTasks();
            });
            taskList.appendChild(li);
        });

        const active = tasks.filter(t => !t.done).length;
        const limitMsg = document.getElementById('g-task-limit');
        if (taskForm) taskForm.classList.toggle('hidden', active >= 3);
        if (limitMsg) limitMsg.classList.toggle('hidden', active < 3);
    }

    if (taskForm) {
        taskForm.addEventListener('submit', e => {
            e.preventDefault();
            const titleEl = document.getElementById('g-task-title');
            const estEl = document.getElementById('g-task-est');
            const title = titleEl.value.trim();
            if (!title) return;
            const all = Store.get(K.tasks, []);
            all.push({ id: Store.uid(), title: title, est: estEl.value || null, done: false, date: today });
            Store.set(K.tasks, all);
            titleEl.value = ''; estEl.value = '';
            renderTasks();
        });
        renderTasks();
    }

    /* ---- Mood check-in ---- */
    const moodWrap = document.getElementById('g-mood-form-wrap');
    const moodDone = document.getElementById('g-mood-done');
    const emojis = { 1: '😫', 2: '😕', 3: '😐', 4: '🙂', 5: '😄' };
    let selectedMood = null;

    function renderMood() {
        if (!moodWrap) return;
        const todayMood = Store.get(K.moods, []).find(m => m.date === today);
        if (todayMood) {
            moodWrap.classList.add('hidden');
            moodDone.classList.remove('hidden');
            document.getElementById('g-mood-emoji').textContent = emojis[todayMood.score] || '🙂';
            const noteEl = document.getElementById('g-mood-note');
            if (todayMood.note) {
                noteEl.textContent = '"' + todayMood.note + '"';
                noteEl.classList.remove('hidden');
            }
        }
    }

    if (moodWrap) {
        moodWrap.querySelectorAll('.mood-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                moodWrap.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedMood = parseInt(this.dataset.value, 10);
            });
        });
        document.getElementById('g-mood-save').addEventListener('click', () => {
            if (!selectedMood) { Store.toast(TJ('js_pick_mood', 'Pick an emoji first 🙂')); return; }
            const moods = Store.get(K.moods, []);
            moods.push({ score: selectedMood, note: document.getElementById('g-mood-note-input').value.trim(), date: today });
            Store.set(K.moods, moods);
            Store.addXP(5);
            renderMood(); renderStats();
        });
        renderMood();
    }
});
