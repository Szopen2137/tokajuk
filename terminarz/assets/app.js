const loginView = document.getElementById('loginView');
const appView = document.getElementById('appView');
const loginForm = document.getElementById('loginForm');
const logoutButton = document.getElementById('logoutButton');
const prevMonthButton = document.getElementById('prevMonthButton');
const nextMonthButton = document.getElementById('nextMonthButton');
const calendarGrid = document.getElementById('calendarGrid');
const calendarTitle = document.getElementById('calendarTitle');
const selectedDayLabel = document.getElementById('selectedDayLabel');
const entryForm = document.getElementById('entryForm');
const resetButton = document.getElementById('resetButton');
const entriesBody = document.getElementById('entriesBody');
const categoryId = document.getElementById('categoryId');
const message = document.getElementById('message');
const welcomeText = document.getElementById('welcomeText');
const entryId = document.getElementById('entryId');
const title = document.getElementById('title');
const description = document.getElementById('description');
const startAt = document.getElementById('startAt');
const endAt = document.getElementById('endAt');
const reminderEmail = document.getElementById('reminderEmail');
const reminderMinutes = document.getElementById('reminderMinutes');
const saveButton = document.getElementById('saveButton');

const monthNames = [
    'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
    'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia',
];

let state = {
    categories: [],
    entries: [],
    user: null,
    currentMonth: new Date(),
    selectedDay: null,
};

function api(action, data = null, method = 'POST') {
    const options = {
        method,
        headers: {},
    };

    if (data instanceof FormData) {
        options.body = data;
    } else if (data) {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        options.body = new URLSearchParams(data);
    }

    return fetch(`api.php?action=${encodeURIComponent(action)}`, options)
        .then((response) => response.json().then((payload) => ({ response, payload })))
        .then(({ response, payload }) => {
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'Wystąpił błąd.');
            }

            return payload;
        });
}

function showMessage(text, type = 'info') {
    message.textContent = text;
    message.className = `message ${type}`;
}

function setLoading(isLoading) {
    saveButton.disabled = isLoading;
    resetButton.disabled = isLoading;
    logoutButton.disabled = isLoading;
    prevMonthButton.disabled = isLoading;
    nextMonthButton.disabled = isLoading;
}

function escapeHtml(text) {
    return String(text)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function todayKey() {
    return new Date().toISOString().slice(0, 10);
}

function toDateKey(value) {
    return value ? value.slice(0, 10) : '';
}

function toInputValue(value) {
    return value ? value.slice(0, 16) : '';
}

function formatDayLabel(dateKey) {
    if (!dateKey) {
        return 'Wybierz dzień w kalendarzu';
    }

    const [year, month, day] = dateKey.split('-').map(Number);
    return `${day}. ${monthNames[month - 1]} ${year}`;
}

function monthTitle(date) {
    return `${monthNames[date.getMonth()]} ${date.getFullYear()}`;
}

function renderCategories(categories) {
    categoryId.innerHTML = categories.map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`).join('');
}

function sameDay(entry, dateKey) {
    return toDateKey(entry.start_at) === dateKey;
}

function entriesForSelectedDay() {
    if (!state.selectedDay) {
        return state.entries;
    }

    return state.entries.filter((entry) => sameDay(entry, state.selectedDay));
}

function renderSelectedDayLabel() {
    selectedDayLabel.textContent = state.selectedDay
        ? `${formatDayLabel(state.selectedDay)} - ${entriesForSelectedDay().length} wpisy`
        : 'Wybierz dzień w kalendarzu';
}

function buildCalendarDays() {
    const year = state.currentMonth.getFullYear();
    const month = state.currentMonth.getMonth();
    const firstDay = new Date(year, month, 1);
    const leadingDays = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells = [];

    for (let i = 0; i < leadingDays; i += 1) {
        cells.push('<div class="day-cell empty"></div>');
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const dateKey = `${String(year).padStart(4, '0')}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const count = state.entries.filter((entry) => sameDay(entry, dateKey)).length;
        const classes = ['day-cell'];

        if (dateKey === todayKey()) {
            classes.push('today');
        }

        if (state.selectedDay === dateKey) {
            classes.push('selected');
        }

        cells.push(`
            <button type="button" class="${classes.join(' ')}" data-day="${dateKey}">
                <span class="day-number">${day}</span>
                <span class="day-count">${count ? `${count} wpis` : 'brak'}</span>
            </button>
        `);
    }

    return cells.join('');
}

function renderCalendar() {
    calendarTitle.textContent = monthTitle(state.currentMonth);
    calendarGrid.innerHTML = buildCalendarDays();
    renderSelectedDayLabel();
}

function renderEntries(entries) {
    if (!entries.length) {
        entriesBody.innerHTML = '<tr><td colspan="6" class="empty">Brak wpisów dla wybranego dnia</td></tr>';
        return;
    }

    entriesBody.innerHTML = entries.map((entry) => {
        const reminder = entry.reminder_email
            ? `${entry.reminder_email}${entry.reminder_minutes !== null ? ` (${entry.reminder_minutes} min)` : ''}${entry.reminder_sent ? ' wysłane' : ''}`
            : 'Brak';

        // show description under title so it is visible without extra horizontal space
        const description = entry.description ? `<div class="entry-desc">${escapeHtml(entry.description)}</div>` : '';

        // shorten datetime display to be more compact
        function formatShortDatetime(value) {
            if (!value) return '';
            // support 'YYYY-MM-DD HH:MM:SS' and 'YYYY-MM-DDTHH:MM'
            const normalized = value.replace('T', ' ');
            const [datePart, timePart] = normalized.split(' ');
            if (!datePart) return escapeHtml(value);
            const [y, m, d] = datePart.split('-');
            const hhmm = (timePart || '').slice(0,5);
            return `${d}.${m} ${hhmm}`;
        }

        const start = escapeHtml(formatShortDatetime(entry.start_at));
        const end = escapeHtml(formatShortDatetime(entry.end_at));

        return `
            <tr>
                <td class="entry-title">${escapeHtml(entry.title)}${description}</td>
                <td>${escapeHtml(entry.category_name)}</td>
                <td>${start}</td>
                <td>${end}</td>
                <td>${escapeHtml(reminder)}</td>
                <td class="row-actions">
                    <button type="button" class="btn btn-sm btn-outline-info" data-edit="${entry.id}">Edytuj</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-delete="${entry.id}">Usuń</button>
                </td>
            </tr>
        `;
    }).join('');
}

function fillForm(entry) {
    entryId.value = entry ? entry.id : '';
    title.value = entry ? entry.title : '';
    description.value = entry ? entry.description : '';
    categoryId.value = entry ? String(entry.category_id) : String(state.categories[0]?.id || '');
    startAt.value = entry ? toInputValue(entry.start_at) : '';
    endAt.value = entry ? toInputValue(entry.end_at) : '';
    reminderEmail.value = entry ? (entry.reminder_email || '') : '';
    reminderMinutes.value = entry && entry.reminder_minutes !== null ? entry.reminder_minutes : '';
    saveButton.textContent = entry ? 'Aktualizuj wpis' : 'Zapisz wpis';
}

function resetForm() {
    fillForm(null);
}

async function loadData() {
    const payload = await api('list', null, 'GET');
    state.categories = payload.categories;
    state.entries = payload.entries;
    renderCategories(state.categories);

    if (!state.selectedDay || !state.entries.some((entry) => sameDay(entry, state.selectedDay))) {
        state.selectedDay = todayKey();
    }

    renderCalendar();
    renderEntries(entriesForSelectedDay());

    if (!entryId.value) {
        resetForm();
    }
}

function setLoggedIn(user) {
    state.user = user;
    loginView.classList.add('hidden');
    appView.classList.remove('hidden');
    welcomeText.textContent = `Panel terminarza: ${user.username}`;
}

function setLoggedOut() {
    state.user = null;
    appView.classList.add('hidden');
    loginView.classList.remove('hidden');
}

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setLoading(true);
    showMessage('Logowanie...', 'info');
    try {
        const payload = await api('login', Object.fromEntries(new FormData(loginForm)));
        setLoggedIn(payload.user);
        state.categories = payload.categories;
        state.entries = payload.entries;
        state.currentMonth = new Date();
        state.selectedDay = todayKey();
        renderCategories(state.categories);
        renderCalendar();
        renderEntries(entriesForSelectedDay());
        resetForm();
        showMessage('Zalogowano pomyślnie.', 'success');
    } catch (error) {
        showMessage(error.message, 'error');
    } finally {
        setLoading(false);
    }
});

logoutButton.addEventListener('click', async () => {
    setLoading(true);
    try {
        await api('logout', {});
        setLoggedOut();
        showMessage('Wylogowano.', 'success');
    } catch (error) {
        showMessage(error.message, 'error');
    } finally {
        setLoading(false);
    }
});

entryForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setLoading(true);
    showMessage('Zapisywanie wpisu...', 'info');
    try {
        await api('save', Object.fromEntries(new FormData(entryForm)));
        await loadData();
        resetForm();
        showMessage('Wpis zapisany.', 'success');
    } catch (error) {
        showMessage(error.message, 'error');
    } finally {
        setLoading(false);
    }
});

resetButton.addEventListener('click', () => {
    resetForm();
    showMessage('Formularz wyczyszczony.', 'info');
});

prevMonthButton.addEventListener('click', () => {
    state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
    renderCalendar();
});

nextMonthButton.addEventListener('click', () => {
    state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
    renderCalendar();
});

calendarGrid.addEventListener('click', (event) => {
    const dayButton = event.target.closest('[data-day]');
    if (!dayButton) {
        return;
    }

    state.selectedDay = dayButton.getAttribute('data-day');
    renderCalendar();
    renderEntries(entriesForSelectedDay());
    showMessage(`Wybrano dzień ${formatDayLabel(state.selectedDay)}.`, 'info');
});

entriesBody.addEventListener('click', async (event) => {
    const actionButton = event.target.closest('button[data-edit], button[data-delete]');
    if (!actionButton) {
        return;
    }

    const editId = actionButton.getAttribute('data-edit');
    const deleteId = actionButton.getAttribute('data-delete');

    if (editId) {
        const entry = state.entries.find((item) => String(item.id) === String(editId));
        if (entry) {
            fillForm(entry);
            showMessage('Edytujesz wybrany wpis.', 'info');
        }
        return;
    }

    if (deleteId) {
        if (!confirm('Usunąć ten wpis?')) {
            return;
        }

        setLoading(true);
        try {
            await api('delete', { id: deleteId });
            await loadData();
            resetForm();
            showMessage('Wpis usunięty.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            setLoading(false);
        }
    }
});

(async function init() {
    fillForm(null);

    if (window.__BOOTSTRAP__?.loggedIn) {
        try {
            const payload = await api('bootstrap', null, 'GET');
            setLoggedIn(payload.user);
            state.categories = payload.categories;
            state.entries = payload.entries;
            state.currentMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            state.selectedDay = todayKey();
            renderCategories(state.categories);
            renderCalendar();
            renderEntries(entriesForSelectedDay());
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }
})();
