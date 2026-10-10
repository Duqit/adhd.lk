// i18n lookup with English fallback
var TJ = function (k, fb) { return (window.ADHD_I18N && window.ADHD_I18N[k]) || fb; };

/* habits-guest.js — localStorage habit tracker for guests. */

document.addEventListener('DOMContentLoaded', () => {
    if (window.ADHD_USER) return;

    const K = Store.KEYS;
    const today = Store.today();
    const list = document.getElementById('habit-list');
    const emptyMsg = document.getElementById('g-habits-empty');
    const form = document.getElementById('g-habit-form');
    const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

    function week() {
        const days = [];
        for (let i = 6; i >= 0; i--) {
            const date = Store.dateOffset(-i);
            const d = new Date(date + 'T00:00:00');
            days.push({
                date: date,
                label: d.toLocaleDateString('en-US', { weekday: 'short' })[0],
                num: d.getDate()
            });
        }
        return days;
    }

    function streakOf(logs) {
        const set = new Set(logs);
        let streak = 0;
        let cursor = set.has(today) ? 0 : -1;
        while (set.has(Store.dateOffset(cursor - streak))) streak++;
        return streak;
    }

    function render() {
        const habits = Store.get(K.habits, []);
        const logs = Store.get(K.habitLogs, {});
        list.innerHTML = '';
        emptyMsg.classList.toggle('hidden', habits.length > 0);

        habits.forEach(h => {
            const hLogs = logs[h.id] || [];
            const streak = streakOf(hLogs);
            const checkedToday = hLogs.includes(today);

            const row = document.createElement('div');
            row.className = 'habit-row';
            row.innerHTML =
                '<div class="habit-head">' +
                    '<div class="habit-emoji">' + esc(h.emoji || '✅') + '</div>' +
                    '<div class="habit-info">' +
                        '<div class="habit-name">' + esc(h.name) + '</div>' +
                        '<div class="habit-streak">🔥 <strong>' + streak + ' ' + (streak === 1 ? TJ('js_day', 'day') : TJ('js_days', 'days')) + '</strong> ' + TJ('js_streak', 'streak') + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="habit-week">' +
                    week().map(d =>
                        '<div class="habit-day' + (hLogs.includes(d.date) ? ' done' : '') + (d.date === today ? ' today' : '') + '">' +
                            '<span>' + d.label + '</span><span class="d-num">' + d.num + '</span>' +
                        '</div>'
                    ).join('') +
                '</div>' +
                '<div class="habit-actions">' +
                    '<button class="habit-check-btn' + (checkedToday ? ' checked' : '') + '" style="flex:1;">' + (checkedToday ? TJ('js_done', '✓ Done') : TJ('js_check_in', 'Check in')) + '</button>' +
                    '<button class="task-del" title="Delete">&times;</button>' +
                '</div>';

            row.querySelector('.habit-check-btn').addEventListener('click', () => {
                const all = Store.get(K.habitLogs, {});
                const cur = all[h.id] || [];
                if (cur.includes(today)) {
                    all[h.id] = cur.filter(d => d !== today);
                } else {
                    cur.push(today);
                    all[h.id] = cur;
                    Store.addXP(5);
                }
                Store.set(K.habitLogs, all);
                render();
            });

            row.querySelector('.task-del').addEventListener('click', () => {
                if (!confirm(TJ('js_confirm_del_habit', 'Delete this habit and its history?'))) return;
                Store.set(K.habits, Store.get(K.habits, []).filter(x => x.id !== h.id));
                const all = Store.get(K.habitLogs, {});
                delete all[h.id];
                Store.set(K.habitLogs, all);
                render();
            });

            list.appendChild(row);
        });
    }

    if (form) {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const name = document.getElementById('g-habit-name').value.trim();
            const emoji = document.getElementById('g-habit-emoji').value.trim() || '✅';
            if (!name) return;
            const habits = Store.get(K.habits, []);
            habits.push({ id: Store.uid(), name: name, emoji: emoji, created: today });
            Store.set(K.habits, habits);
            form.reset();
            render();
        });
    }

    render();
});
