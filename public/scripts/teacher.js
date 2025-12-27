/**
 * Teacher module - panel nauczyciela
 */
let selectedClassId = null;
let selectedDeckId = null;

document.addEventListener('DOMContentLoaded', () => {
    initTeacherPanel();
});

async function initTeacherPanel() {
    await loadTeacherClasses();
    setupTabs();
    setupForms();
}

/**
 * Ładuje klasy nauczyciela
 */
async function loadTeacherClasses() {
    const container = document.getElementById('teacherClasses');
    if (!container) return;
    
    try {
        const result = await API.classes.list();
        
        if (result.ok) {
            renderTeacherClasses(result.data);
        } else {
            container.innerHTML = '<p class="error">Błąd ładowania klas</p>';
        }
    } catch (error) {
        console.error('Error loading classes:', error);
        container.innerHTML = '<p class="error">Błąd ładowania</p>';
    }
}

function renderTeacherClasses(classes) {
    const container = document.getElementById('teacherClasses');
    
    if (classes.length === 0) {
        container.innerHTML = '<p class="no-data">Nie masz jeszcze żadnych klas. Utwórz pierwszą!</p>';
        return;
    }
    
    container.innerHTML = classes.map(c => `
        <div class="class-card" onclick="selectClass(${c.id}, '${escapeHtml(c.name)}', '${c.joinCode}')">
            <div class="class-card-header">
                <span class="flag">${getLanguageFlag(c.language)}</span>
                <h3>${escapeHtml(c.name)}</h3>
            </div>
            <p class="join-code">Kod: <strong>${c.joinCode}</strong></p>
        </div>
    `).join('');
}

/**
 * Wybiera klasę do edycji
 */
async function selectClass(classId, className, joinCode) {
    selectedClassId = classId;
    
    document.getElementById('selectedClassName').textContent = className;
    document.getElementById('classJoinCode').textContent = `Kod: ${joinCode}`;
    document.getElementById('selectedClassSection').style.display = 'block';
    
    // Załaduj decki
    await loadDecks(classId);
}

/**
 * Ładuje decki dla klasy
 */
async function loadDecks(classId) {
    const container = document.getElementById('decksList');
    
    try {
        const result = await API.decks.listByClass(classId);
        
        if (result.ok) {
            renderDecks(result.data);
        }
    } catch (error) {
        console.error('Error loading decks:', error);
    }
}

function renderDecks(decks) {
    const container = document.getElementById('decksList');
    
    if (decks.length === 0) {
        container.innerHTML = '<p class="no-data">Brak zestawów. Dodaj pierwszy!</p>';
        return;
    }
    
    container.innerHTML = decks.map(d => `
        <div class="deck-card">
            <div class="deck-info">
                <h4>${escapeHtml(d.title)} ${d.isPublic ? '<span class="public-badge">🌍 Publiczny</span>' : ''}</h4>
                <p>${d.description || 'Brak opisu'}</p>
                <span class="level level-${d.level}">${getLevelLabel(d.level)}</span>
                <span class="card-count">${d.cardCount || 0} fiszek</span>
                ${d.isPublic ? `<span class="rating-info">⭐ ${(d.averageRating || 0).toFixed(1)} (${d.ratingsCount || 0})</span>` : ''}
            </div>
            <div class="deck-actions">
                <button class="btn-sm btn-primary" onclick="showCardsManager(${d.id}, '${escapeHtml(d.title)}')">Zarządzaj fiszkami</button>
                <button class="btn-sm btn-secondary" onclick="toggleDeckPublic(${d.id}, ${!d.isPublic})">${d.isPublic ? 'Ukryj' : 'Upublicznij'}</button>
                ${d.isPublic && d.shareToken ? `<button class="btn-sm btn-secondary" onclick="copyShareLink('${d.shareToken}')">Kopiuj link</button>` : ''}
                <button class="btn-sm btn-danger" onclick="deleteDeck(${d.id})">Usuń</button>
            </div>
        </div>
    `).join('');
}

/**
 * Pokazuje manager fiszek
 */
async function showCardsManager(deckId, deckTitle) {
    selectedDeckId = deckId;
    
    try {
        const result = await API.decks.cards(deckId);
        
        if (result.ok) {
            const cardsHtml = result.data.length === 0 
                ? '<p class="no-data">Brak fiszek</p>'
                : result.data.map(c => `
                    <div class="card-item">
                        <span class="card-front">${escapeHtml(c.front)}</span>
                        <span class="separator">→</span>
                        <span class="card-back">${escapeHtml(c.back)}</span>
                    </div>
                `).join('');
            
            document.getElementById('decksList').innerHTML = `
                <div class="cards-manager">
                    <div class="manager-header">
                        <button class="btn-sm" onclick="loadDecks(${selectedClassId})">← Wróć</button>
                        <h3>Fiszki: ${deckTitle}</h3>
                        <button class="btn-primary btn-sm" onclick="showCreateCardModal()">+ Dodaj fiszkę</button>
                    </div>
                    <div class="cards-list">${cardsHtml}</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading cards:', error);
    }
}

/**
 * Zakładki
 */
function setupTabs() {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            
            tab.classList.add('active');
            const tabId = tab.dataset.tab + 'Tab';
            document.getElementById(tabId).style.display = 'block';
            
            // Załaduj zawartość zakładki
            if (tab.dataset.tab === 'tasks' && selectedClassId) {
                loadTasks(selectedClassId);
            } else if (tab.dataset.tab === 'members' && selectedClassId) {
                loadMembers(selectedClassId);
            }
        });
    });
}

/**
 * Ładuje zadania
 */
async function loadTasks(classId) {
    const container = document.getElementById('tasksList');
    
    try {
        const result = await API.classes.tasks(classId);
        
        if (result.ok) {
            if (result.data.length === 0) {
                container.innerHTML = '<p class="no-data">Brak zadań</p>';
            } else {
                container.innerHTML = result.data.map(t => `
                    <div class="task-item">
                        <h4>${escapeHtml(t.title)}</h4>
                        <p>${t.description || ''}</p>
                        ${t.dueDate ? `<span class="due-date">Termin: ${new Date(t.dueDate).toLocaleDateString('pl-PL')}</span>` : ''}
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
    }
}

/**
 * Ładuje członków klasy
 */
async function loadMembers(classId) {
    const container = document.getElementById('membersList');
    
    try {
        const result = await API.classes.members(classId);
        
        if (result.ok) {
            if (result.data.length === 0) {
                container.innerHTML = '<p class="no-data">Brak uczniów w klasie</p>';
            } else {
                container.innerHTML = result.data.map(m => `
                    <div class="member-item">
                        <span class="member-name">${escapeHtml(m.firstname)} ${escapeHtml(m.lastname)}</span>
                        <span class="member-email">${escapeHtml(m.email)}</span>
                        <button class="btn-sm btn-danger" onclick="removeMember(${classId}, ${m.id})">Usuń</button>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading members:', error);
    }
}

/**
 * Usuwa ucznia z klasy
 */
async function removeMember(classId, studentId) {
    showConfirmModal(
        '👤 Usuń ucznia',
        'Czy na pewno chcesz usunąć tego ucznia z klasy?',
        async () => {
            try {
                const result = await API.delete(`/api/classes/${classId}/members/${studentId}`);
                
                if (result.ok) {
                    await loadMembers(classId);
                    showToast('Uczeń został usunięty z klasy', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się usunąć ucznia'), 'error');
                }
            } catch (error) {
                console.error('Error removing member:', error);
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

/**
 * Usuwa klasę
 */
async function deleteClass() {
    if (!selectedClassId) {
        showToast('Wybierz najpierw klasę', 'error');
        return;
    }
    
    showConfirmModal(
        '🗑️ Usuń klasę',
        'Czy na pewno chcesz usunąć tę klasę? Wszystkie zestawy, fiszki i zadania zostaną trwale usunięte!',
        async () => {
            try {
                const result = await API.delete(`/api/classes/${selectedClassId}`);
                
                if (result.ok) {
                    selectedClassId = null;
                    document.getElementById('selectedClassSection').style.display = 'none';
                    await loadTeacherClasses();
                    showToast('Klasa została usunięta', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się usunąć klasy'), 'error');
                }
            } catch (error) {
                console.error('Error deleting class:', error);
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

/**
 * Formularze
 */
function setupForms() {
    // Tworzenie klasy
    document.getElementById('createClassForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        
        try {
            const result = await API.classes.create({
                name: form.name.value,
                description: form.description.value,
                language: form.language.value
            });
            
            if (result.ok) {
                showToast(`Klasa utworzona! Kod dołączenia: ${result.data.joinCode}`, 'success');
                closeModal('createClassModal');
                form.reset();
                await loadTeacherClasses();
            } else {
                showToast('Błąd: ' + (result.error?.message || 'Nie udało się utworzyć klasy'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Wystąpił błąd', 'error');
        }
    });
    
    // Tworzenie decku
    document.getElementById('createDeckForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        
        if (!selectedClassId) {
            showToast('Wybierz najpierw klasę', 'error');
            return;
        }
        
        try {
            const result = await API.decks.create(selectedClassId, {
                title: form.title.value,
                description: form.description.value,
                level: form.level.value,
                imageUrl: form.imageUrl?.value || null,
                isPublic: form.isPublic?.checked || false
            });
            
            if (result.ok) {
                closeModal('createDeckModal');
                form.reset();
                await loadDecks(selectedClassId);
                if (form.isPublic?.checked) {
                    showToast('Zestaw utworzony i udostępniony publicznie! 🌍', 'success');
                } else {
                    showToast('Zestaw został utworzony', 'success');
                }
            } else {
                showToast('Błąd: ' + (result.error?.message || 'Nie udało się utworzyć zestawu'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Wystąpił błąd', 'error');
        }
    });
    
    // Tworzenie fiszki
    document.getElementById('createCardForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        
        if (!selectedDeckId) {
            showToast('Wybierz najpierw zestaw', 'error');
            return;
        }
        
        try {
            const result = await API.decks.createCard(selectedDeckId, {
                front: form.front.value,
                back: form.back.value
            });
            
            if (result.ok) {
                closeModal('createCardModal');
                form.reset();
                showToast('Fiszka została dodana', 'success');
                // Odśwież listę fiszek
                const deck = await API.decks.get(selectedDeckId);
                if (deck.ok) {
                    showCardsManager(selectedDeckId, deck.data.title);
                }
            } else {
                showToast('Błąd: ' + (result.error?.message || 'Nie udało się dodać fiszki'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Wystąpił błąd', 'error');
        }
    });
    
    // Tworzenie zadania
    document.getElementById('createTaskForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        
        if (!selectedClassId) {
            showToast('Wybierz najpierw klasę', 'error');
            return;
        }
        
        const deckId = form.deckId.value;
        if (!deckId) {
            showToast('Wybierz zestaw fiszek', 'error');
            return;
        }
        
        try {
            const result = await API.classes.createTask(selectedClassId, {
                title: form.title.value,
                description: form.description.value,
                deckId: parseInt(deckId),
                dueDate: form.dueDate.value
            });
            
            if (result.ok) {
                closeModal('createTaskModal');
                form.reset();
                showToast('Zadanie zostało dodane!', 'success');
                // Odśwież widok klasy jeśli jest widoczny
                if (selectedClassId) {
                    await loadClassDetails(selectedClassId);
                }
            } else {
                showToast('Błąd: ' + (result.error?.message || 'Nie udało się dodać zadania'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Wystąpił błąd', 'error');
        }
    });
}

/**
 * Usuwa deck
 */
async function deleteDeck(deckId) {
    showConfirmModal(
        '🗑️ Usuń zestaw',
        'Czy na pewno chcesz usunąć ten zestaw fiszek? Tej operacji nie można cofnąć.',
        async () => {
            try {
                const result = await API.decks.delete(deckId);
                
                if (result.ok) {
                    await loadDecks(selectedClassId);
                    showToast('Zestaw został usunięty', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się usunąć'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

/**
 * Modale
 */
function showCreateClassModal() {
    document.getElementById('createClassModal').style.display = 'flex';
}

function showCreateDeckModal() {
    if (!selectedClassId) {
        showToast('Wybierz najpierw klasę', 'error');
        return;
    }
    document.getElementById('createDeckModal').style.display = 'flex';
}

function showCreateCardModal() {
    if (!selectedDeckId) {
        showToast('Wybierz najpierw zestaw', 'error');
        return;
    }
    document.getElementById('createCardModal').style.display = 'flex';
}

async function showCreateTaskModal() {
    if (!selectedClassId) {
        showToast('Najpierw wybierz klasę', 'error');
        return;
    }
    
    // Pobierz decki dla tej klasy i wypełnij select
    const deckSelect = document.getElementById('taskDeck');
    deckSelect.innerHTML = '<option value="">-- Ładowanie... --</option>';
    
    try {
        const result = await API.decks.listByClass(selectedClassId);
        if (result.ok && result.data?.decks?.length > 0) {
            deckSelect.innerHTML = '<option value="">-- Wybierz zestaw --</option>';
            result.data.decks.forEach(deck => {
                const option = document.createElement('option');
                option.value = deck.id;
                option.textContent = deck.title;
                deckSelect.appendChild(option);
            });
        } else {
            deckSelect.innerHTML = '<option value="">-- Brak zestawów --</option>';
            showToast('Najpierw dodaj zestawy fiszek', 'error');
            return;
        }
    } catch (error) {
        console.error('Error loading decks:', error);
        showToast('Błąd ładowania zestawów', 'error');
        return;
    }
    
    // Ustaw domyślny termin na za tydzień
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    nextWeek.setHours(23, 59);
    document.getElementById('taskDueDate').value = nextWeek.toISOString().slice(0, 16);
    
    // Wyczyść formularz
    document.getElementById('taskTitle').value = '';
    document.getElementById('taskDescription').value = '';
    
    document.getElementById('createTaskModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

/**
 * Przełącza status publiczny decku
 */
async function toggleDeckPublic(deckId, makePublic) {
    const action = makePublic ? 'upublicznić' : 'ukryć';
    const message = makePublic 
        ? 'Zestaw będzie widoczny dla wszystkich użytkowników w sekcji Społeczność. Czy kontynuować?'
        : 'Zestaw przestanie być widoczny publicznie. Czy kontynuować?';
    
    showConfirmModal(
        makePublic ? '🌍 Upublicznij zestaw' : '🔒 Ukryj zestaw',
        message,
        async () => {
            try {
                const result = await API.put(`/api/decks/${deckId}`, {
                    isPublic: makePublic
                });
                
                if (result.ok) {
                    await loadDecks(selectedClassId);
                    showToast(makePublic ? 'Zestaw został upubliczniony!' : 'Zestaw został ukryty.', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się zmienić statusu'), 'error');
                }
            } catch (error) {
                console.error('Error toggling public status:', error);
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

/**
 * Kopiuje link do udostępniania
 */
function copyShareLink(shareToken) {
    const url = `${window.location.origin}/community?share=${shareToken}`;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link skopiowany do schowka!', 'success');
    }).catch(() => {
        prompt('Skopiuj ten link:', url);
    });
}

// Zamykanie modali kliknięciem poza
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});

/**
 * Helpers
 */
function getLanguageFlag(lang) {
    if (!lang) return '📚';
    const language = lang.toLowerCase();
    const flags = {
        'de': '🇩🇪', 'en': '🇬🇧', 'es': '🇪🇸', 'fr': '🇫🇷',
        'it': '🇮🇹', 'ru': '🇷🇺', 'pl': '🇵🇱', 'ja': '🇯🇵', 'zh': '🇨🇳'
    };
    return flags[language] || '📚';
}

function getLevelLabel(level) {
    const labels = {
        'beginner': 'Początkujący',
        'intermediate': 'Średni',
        'advanced': 'Zaawansowany'
    };
    return labels[level] || level;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Alias dla przycisku w HTML
function deleteSelectedClass() {
    deleteClass();
}
