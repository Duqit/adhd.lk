// i18n lookup with English fallback
var TJ = function (k, fb) { return (window.ADHD_I18N && window.ADHD_I18N[k]) || fb; };

/* routines-guest.js — localStorage routines for guests.
   Reuses the fullscreen runner from routines.js (steps use duration_minutes). */

document.addEventListener('DOMContentLoaded', () => {
    if (window.ADHD_USER) return;

    const K = Store.KEYS;
    const grid = document.getElementById('routine-grid');
    const emptyMsg = document.getElementById('g-routines-empty');
    const form = document.getElementById('routine-form');
    const formCard = document.getElementById('create-routine-form');
    const btnCreate = document.getElementById('btn-create-routine');
    const templatesBox = document.getElementById('routine-templates');
    const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

    const TEMPLATES = {
        morning: { name: TJ('js_tpl_morning', 'Morning Launch'), steps: [
            { title: 'Drink a glass of water', duration_minutes: 2 },
            { title: 'Make bed', duration_minutes: 3 },
            { title: 'Shower & dress', duration_minutes: 12 },
            { title: 'Breakfast (no phone)', duration_minutes: 8 }
        ]},
        shutdown: { name: TJ('js_tpl_shutdown', 'Evening Shutdown'), steps: [
            { title: 'Write tomorrow\'s Top 3', duration_minutes: 5 },
            { title: 'Tidy one surface', duration_minutes: 5 },
            { title: 'Prepare clothes & bag', duration_minutes: 5 },
            { title: 'Screens off, wind down', duration_minutes: 15 }
        ]},
        desk: { name: TJ('js_tpl_desk', '5-Minute Desk Reset'), steps: [
            { title: 'Trash & dishes off desk', duration_minutes: 2 },
            { title: 'Close extra browser tabs', duration_minutes: 1 },
            { title: 'Write down next action', duration_minutes: 2 }
        ]}
    };

    function getRoutines() { return Store.get(K.routines, []); }

    function render() {
        const routines = getRoutines();
        grid.innerHTML = '';
        emptyMsg.classList.toggle('hidden', routines.length > 0);
        if (templatesBox) templatesBox.classList.toggle('hidden', routines.length > 0);

        routines.forEach(r => {
            const total = r.steps.reduce((sum, s) => sum + parseInt(s.duration_minutes, 10), 0);
            const card = document.createElement('div');
            card.className = 'glass-card routine-card';
            card.innerHTML =
                '<div style="display:flex; justify-content:space-between; align-items:flex-start;">' +
                    '<h3>' + esc(r.name) + '</h3>' +
                    '<button class="icon-btn g-del" style="font-size:1.5rem;">&times;</button>' +
                '</div>' +
                '<p style="color:var(--text-secondary); font-size:0.9rem;">' + TJ('rout_total', 'Total Time') + ': ' + total + ' ' + TJ('rout_mins', 'mins') + '</p>' +
                '<ul class="step-list">' +
                    r.steps.map(s =>
                        '<li class="step-item"><span>' + esc(s.title) + '</span>' +
                        '<span style="color:var(--text-secondary); font-weight:600;">' + parseInt(s.duration_minutes, 10) + 'm</span></li>'
                    ).join('') +
                '</ul>' +
                '<button class="btn btn-primary g-start" style="width:100%; margin-top:1rem;">' + TJ('rout_start', 'Start Routine') + '</button>';

            card.querySelector('.g-del').addEventListener('click', () => {
                if (!confirm(TJ('js_confirm_del_routine', 'Delete this routine?'))) return;
                Store.set(K.routines, getRoutines().filter(x => x.id !== r.id));
                render();
            });
            card.querySelector('.g-start').addEventListener('click', () => {
                window.startRoutine({ name: r.name, steps: r.steps });
            });
            grid.appendChild(card);
        });
    }

    function saveRoutine(name, steps) {
        const routines = getRoutines();
        routines.unshift({ id: Store.uid(), name: name, steps: steps });
        Store.set(K.routines, routines);
        Store.toast(TJ('js_routine_saved', 'Routine saved ✔'));
        render();
    }

    if (form) {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const name = document.getElementById('routine-name-input').value.trim();
            if (!name) return;
            const titles = form.querySelectorAll('input[name="step_title[]"]');
            const mins = form.querySelectorAll('input[name="step_duration[]"]');
            const steps = [];
            titles.forEach((t, i) => {
                const title = t.value.trim();
                const dur = parseInt(mins[i].value, 10);
                if (title && dur > 0) steps.push({ title: title, duration_minutes: dur });
            });
            if (steps.length === 0) return;
            saveRoutine(name, steps);
            form.reset();
            formCard.classList.add('hidden');
            if (btnCreate) btnCreate.style.display = 'block';
        });
    }

    document.querySelectorAll('.tpl-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tpl = TEMPLATES[btn.dataset.tpl];
            if (tpl) saveRoutine(tpl.name, tpl.steps.map(s => ({ ...s })));
        });
    });

    render();
});
