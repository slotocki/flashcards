/**
 * Teacher module - panel nauczyciela
 * Zestawy należą do nauczyciela i mogą być przypisane do wielu klas
 */
let selectedClassId = null;
let selectedDeckId = null;
let teacherClasses = [];

document.addEventListener('DOMContentLoaded', () => {
    initTeacherPanel();
});

async function initTeacherPanel() {
    await Promise.all([
        loadTeacherClasses(),
        loadTeacherDecks()
    ]);
    setupTabs();
    setupForms();
}

async function loadTeacherDecks() {
    const container = document.getElementById('teacherDecks');
    if (!container) return;
    
    try {
        const result = await API.decks.listTeacherDecks();
        
        if (result.ok) {
            renderTeacherDecks(result.data);
        } else {
            container.innerHTML = '<p class="error">Błąd ładowania zestawów</p>';
        }
    } catch (error) {
        console.error('Error loading teacher decks:', error);
        container.innerHTML = '<p class="error">Błąd ładowania</p>';
    }
}

function renderTeacherDecks(decks) {
    const container = document.getElementById('teacherDecks');
    
    if (!decks || decks.length === 0) {
        container.innerHTML = '<p class="no-data">Nie masz jeszcze żadnych zestawów. Utwórz pierwszy!</p>';
        return;
    }
    
    container.innerHTML = decks.map(d => {
        const imageHtml = d.imageUrl 
            ? `<img src="${d.imageUrl}" alt="${escapeHtml(d.title)}">`
            : '<div class="deck-placeholder-image">📚</div>';
        
        return `
        <div class="teacher-deck-card" data-deck-id="${d.id}">
            <div class="teacher-deck-image">
                ${imageHtml}
            </div>
            <div class="teacher-deck-content">
                <div class="teacher-deck-header">
                    <h4>${escapeHtml(d.title)}</h4>
                    ${d.isPublic ? '<span class="public-badge">🌍 Publiczny</span>' : ''}
                </div>
                <p class="teacher-deck-desc">${d.description || 'Brak opisu'}</p>
                <div class="teacher-deck-meta">
                    <span class="level level-${d.level}">${getLevelLabel(d.level)}</span>
                    <span class="card-count">${d.cardCount || 0} fiszek</span>
                </div>
                <div class="teacher-deck-actions">
                    <button class="btn-sm btn-primary" onclick="showDeckManageModal(${d.id}, '${escapeHtml(d.title)}', ${d.isPublic}, '${d.shareToken || ''}')">Zarządzaj</button>
                </div>
            </div>
        </div>
    `}).join('');
}

async function loadTeacherClasses() {
    const container = document.getElementById('teacherClasses');
    if (!container) return;
    
    try {
        const result = await API.classes.list();
        
        if (result.ok) {
            teacherClasses = result.data || [];
            renderTeacherClasses(teacherClasses);
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
                ${getLanguageFlag(c.language)}
                <h3>${escapeHtml(c.name)}</h3>
            </div>
            <p class="join-code">Kod: <strong>${c.joinCode}</strong></p>
        </div>
    `).join('');
}

async function selectClass(classId, className, joinCode) {
    selectedClassId = classId;
    
    document.getElementById('selectedClassName').textContent = className;
    document.getElementById('classJoinCode').textContent = `Kod: ${joinCode}`;
    document.getElementById('selectedClassSection').style.display = 'block';
    
    await loadClassDecks(classId);
}

async function loadClassDecks(classId) {
    const container = document.getElementById('decksList');
    
    try {
        const result = await API.decks.listByClass(classId);
        
        if (result.ok) {
            renderClassDecks(result.data);
        }
    } catch (error) {
        console.error('Error loading class decks:', error);
    }
}

function renderClassDecks(decks) {
    const container = document.getElementById('decksList');
    
    if (!decks || decks.length === 0) {
        container.innerHTML = '<p class="no-data">Brak zestawów przypisanych do tej klasy. Przypisz zestawy w sekcji "Moje zestawy".</p>';
        return;
    }
    
    container.innerHTML = decks.map(d => `
        <div class="deck-card">
            <div class="deck-info">
                <h4>${escapeHtml(d.title)}</h4>
                <p>${d.description || 'Brak opisu'}</p>
                <span class="level level-${d.level}">${getLevelLabel(d.level)}</span>
                <span class="card-count">${d.cardCount || 0} fiszek</span>
            </div>
            <div class="deck-actions">
                <button class="btn-sm btn-primary" onclick="showCardsManager(${d.id}, '${escapeHtml(d.title)}')">Zarządzaj fiszkami</button>
                <button class="btn-sm btn-secondary" onclick="unassignDeckFromClass(${d.id})">Odepnij</button>
            </div>
        </div>
    `).join('');
}

async function showCardsManager(deckId, deckTitle) {
    selectedDeckId = deckId;
    
    try {
        const result = await API.decks.cards(deckId);
        
        if (result.ok) {
            const cardsHtml = result.data.length === 0 
                ? '<p class="no-data">Brak fiszek</p>'
                : result.data.map(c => `
                    <div class="card-item">
                        ${c.imagePath ? `<img src="${c.imagePath}" alt="Obrazek" class="card-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px;">` : ''}
                        <span class="card-front">${escapeHtml(c.front)}</span>
                        <span class="separator">→</span>
                        <span class="card-back">${escapeHtml(c.back)}</span>
                    </div>
                `).join('');
            
            const decksContainer = document.getElementById('teacherDecks') || document.getElementById('decksList');
            
            decksContainer.innerHTML = `
                <div class="cards-manager">
                    <div class="manager-header">
                        <button class="btn-sm" onclick="goBackFromCardsManager()">← Wróć</button>
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

function goBackFromCardsManager() {
    selectedDeckId = null;
    loadTeacherDecks();
    if (selectedClassId) {
        loadClassDecks(selectedClassId);
    }
}

async function showAssignClassesModal(deckId) {
    document.getElementById('assignDeckId').value = deckId;
    
    try {
        const deckResult = await API.decks.get(deckId);
        const currentClassIds = deckResult.ok && deckResult.data.classIds ? deckResult.data.classIds : [];
        
        renderClassSelection('assignClassSelection', currentClassIds);
    } catch (error) {
        console.error('Error:', error);
        renderClassSelection('assignClassSelection', []);
    }
    
    document.getElementById('assignClassesModal').style.display = 'flex';
}

function renderClassSelection(containerId, selectedClassIds = []) {
    const container = document.getElementById(containerId);
    
    if (teacherClasses.length === 0) {
        container.innerHTML = '<p class="text-muted">Nie masz jeszcze żadnych klas.</p>';
        return;
    }
    
    container.innerHTML = teacherClasses.map(c => `
        <label class="checkbox-label">
            <input type="checkbox" name="classIds" value="${c.id}" 
                   ${selectedClassIds.includes(c.id) ? 'checked' : ''}>
            ${getLanguageFlag(c.language)} ${escapeHtml(c.name)}
        </label>
    `).join('');
}

function getSelectedClassIds(containerId) {
    const container = document.getElementById(containerId);
    const checkboxes = container.querySelectorAll('input[name="classIds"]:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.value));
}

function setupTabs() {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            
            tab.classList.add('active');
            const tabId = tab.dataset.tab + 'Tab';
            document.getElementById(tabId).style.display = 'block';
            
            if (tab.dataset.tab === 'tasks' && selectedClassId) {
                loadTasks(selectedClassId);
            } else if (tab.dataset.tab === 'members' && selectedClassId) {
                loadMembers(selectedClassId);
            }
        });
    });
}

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
                        <div class="task-content">
                            <h4>${escapeHtml(t.title)}</h4>
                            <p>${t.description || ''}</p>
                            ${t.dueDate ? `<span class="due-date">Termin: ${new Date(t.dueDate).toLocaleDateString('pl-PL')}</span>` : ''}
                        </div>
                        <div class="task-actions">
                            <button class="btn-sm btn-danger" onclick="deleteTask(${classId}, ${t.id})">Usuń</button>
                        </div>
                    </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
    }
}

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

async function deleteTask(classId, taskId) {
    showConfirmModal(
        '🗑️ Usuń zadanie',
        'Czy na pewno chcesz usunąć to zadanie?',
        async () => {
            try {
                const result = await API.classes.deleteTask(classId, taskId);
                
                if (result.ok) {
                    await loadTasks(classId);
                    showToast('Zadanie zostało usunięte', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się usunąć zadania'), 'error');
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

async function deleteClass() {
    if (!selectedClassId) {
        showToast('Wybierz najpierw klasę', 'error');
        return;
    }
    
    showConfirmModal(
        '🗑️ Usuń klasę',
        'Czy na pewno chcesz usunąć tę klasę? Uczniowie stracą do niej dostęp.',
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

async function deleteDeck(deckId) {
    showConfirmModal(
        '🗑️ Usuń zestaw',
        'Czy na pewno chcesz usunąć ten zestaw fiszek? Tej operacji nie można cofnąć.',
        async () => {
            try {
                const result = await API.decks.delete(deckId);
                
                if (result.ok) {
                    await loadTeacherDecks();
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

function setupForms() {
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
    
    document.getElementById('createDeckForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        
        try {
            const imageFile = document.getElementById('deckImage')?.files[0];
            let imageUrl = null;
            
            if (imageFile) {
                const uploadResult = await uploadDeckImage(imageFile);
                if (uploadResult.ok) {
                    imageUrl = uploadResult.path;
                } else {
                    showToast('Błąd uploadu obrazka: ' + uploadResult.error, 'error');
                    return;
                }
            }
            
            const classIds = getSelectedClassIds('deckClassSelection');
            
            const result = await API.decks.create({
                title: form.title.value,
                description: form.description.value,
                level: form.level.value,
                imageUrl: imageUrl,
                isPublic: form.isPublic?.checked || false,
                classIds: classIds
            });
            
            if (result.ok) {
                closeModal('createDeckModal');
                form.reset();
                clearDeckImagePreview();
                await loadTeacherDecks();
                
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
    
    document.getElementById('assignClassesForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const deckId = document.getElementById('assignDeckId').value;
        const classIds = getSelectedClassIds('assignClassSelection');
        
        try {
            const result = await API.decks.assignToClasses(deckId, classIds);
            
            if (result.ok) {
                closeModal('assignClassesModal');
                await loadTeacherDecks();
                showToast('Przypisanie zaktualizowane', 'success');
            } else {
                showToast('Błąd: ' + (result.error?.message || 'Nie udało się zaktualizować'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Wystąpił błąd', 'error');
        }
    });
    
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
                if (selectedClassId) {
                    await loadTasks(selectedClassId);
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

function showCreateClassModal() {
    document.getElementById('createClassModal').style.display = 'flex';
}

function showCreateDeckModal() {
    renderClassSelection('deckClassSelection', []);
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
    
    const deckSelect = document.getElementById('taskDeck');
    deckSelect.innerHTML = '<option value="">-- Ładowanie... --</option>';
    
    try {
        const result = await API.decks.listByClass(selectedClassId);
        if (result.ok && result.data?.length > 0) {
            deckSelect.innerHTML = '<option value="">-- Wybierz zestaw --</option>';
            result.data.forEach(deck => {
                const option = document.createElement('option');
                option.value = deck.id;
                option.textContent = deck.title;
                deckSelect.appendChild(option);
            });
        } else {
            deckSelect.innerHTML = '<option value="">-- Brak zestawów --</option>';
            showToast('Najpierw przypisz zestawy do tej klasy', 'error');
            return;
        }
    } catch (error) {
        console.error('Error loading decks:', error);
        showToast('Błąd ładowania zestawów', 'error');
        return;
    }
    
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    nextWeek.setHours(23, 59);
    document.getElementById('taskDueDate').value = nextWeek.toISOString().slice(0, 16);
    
    document.getElementById('taskTitle').value = '';
    document.getElementById('taskDescription').value = '';
    
    document.getElementById('createTaskModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

async function toggleDeckPublic(deckId, makePublic) {
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
                    await loadTeacherDecks();
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

function copyShareLink(shareToken) {
    const url = `${window.location.origin}/public-deck?token=${shareToken}`;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link skopiowany do schowka!', 'success');
    }).catch(() => {
        prompt('Skopiuj ten link:', url);
    });
}

async function unassignDeckFromClass(deckId) {
    if (!selectedClassId) {
        showToast('Nie wybrano klasy', 'error');
        return;
    }
    
    showConfirmModal(
        '📌 Odepnij zestaw',
        'Czy na pewno chcesz odpiąć ten zestaw od klasy? Zestaw nie zostanie usunięty, tylko odpięty od tej klasy.',
        async () => {
            try {
                // Pobierz aktualne przypisania
                const deckResult = await API.decks.get(deckId);
                if (!deckResult.ok) {
                    showToast('Błąd: nie można pobrać danych zestawu', 'error');
                    return;
                }
                
                const currentClassIds = deckResult.data.classIds || [];
                const newClassIds = currentClassIds.filter(id => id !== selectedClassId);
                
                const result = await API.decks.assignToClasses(deckId, newClassIds);
                
                if (result.ok) {
                    await loadClassDecks(selectedClassId);
                    await loadTeacherDecks();
                    showToast('Zestaw został odpięty od klasy', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się odpiąć zestawu'), 'error');
                }
            } catch (error) {
                console.error('Error unassigning deck:', error);
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

async function uploadDeckImage(file) {
    const maxSize = 2 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!allowedTypes.includes(file.type)) {
        return { ok: false, error: 'Nieprawidłowy format pliku. Dozwolone: JPG, PNG, GIF, WEBP' };
    }
    
    if (file.size > maxSize) {
        return { ok: false, error: 'Plik jest za duży. Maksymalny rozmiar: 2MB' };
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        const response = await fetch('/api/upload/deck-image', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.ok) {
            return { ok: true, path: result.data.path };
        } else {
            return { ok: false, error: result.error?.message || 'Błąd uploadu' };
        }
    } catch (error) {
        console.error('Upload error:', error);
        return { ok: false, error: 'Błąd połączenia' };
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const deckImageInput = document.getElementById('deckImage');
    if (deckImageInput) {
        deckImageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    document.getElementById('deckPreviewImg').src = event.target.result;
                    document.getElementById('deckImagePreview').style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function clearDeckImagePreview() {
    const imageInput = document.getElementById('deckImage');
    const imagePreview = document.getElementById('deckImagePreview');
    const previewImg = document.getElementById('deckPreviewImg');
    
    if (imageInput) imageInput.value = '';
    if (previewImg) previewImg.src = '';
    if (imagePreview) imagePreview.style.display = 'none';
}

function getLanguageFlag(lang) {
    if (!lang) return '<span class="lang-badge">🌐</span>';
    const language = lang.toLowerCase();
    
    // Flagi jako emoji i pełne nazwy języków
    const languageInfo = {
        'de': { flag: '🇩🇪', name: 'Niemiecki' },
        'en': { flag: '🇬🇧', name: 'Angielski' },
        'es': { flag: '🇪🇸', name: 'Hiszpański' },
        'fr': { flag: '🇫🇷', name: 'Francuski' },
        'it': { flag: '🇮🇹', name: 'Włoski' },
        'ru': { flag: '🇷🇺', name: 'Rosyjski' },
        'pl': { flag: '🇵🇱', name: 'Polski' },
        'ja': { flag: '🇯🇵', name: 'Japoński' },
        'zh': { flag: '🇨🇳', name: 'Chiński' },
        'pt': { flag: '🇵🇹', name: 'Portugalski' },
        'nl': { flag: '🇳🇱', name: 'Niderlandzki' },
        'sv': { flag: '🇸🇪', name: 'Szwedzki' },
        'no': { flag: '🇳🇴', name: 'Norweski' },
        'da': { flag: '🇩🇰', name: 'Duński' },
        'fi': { flag: '🇫🇮', name: 'Fiński' },
        'cs': { flag: '🇨🇿', name: 'Czeski' },
        'sk': { flag: '🇸🇰', name: 'Słowacki' },
        'uk': { flag: '🇺🇦', name: 'Ukraiński' },
        'el': { flag: '🇬🇷', name: 'Grecki' },
        'tr': { flag: '🇹🇷', name: 'Turecki' },
        'ar': { flag: '🇸🇦', name: 'Arabski' },
        'ko': { flag: '🇰🇷', name: 'Koreański' },
        'hi': { flag: '🇮🇳', name: 'Hindi' }
    };
    
    const info = languageInfo[language];
    if (info) {
        return `<span class="lang-flag" title="${info.name}">${info.flag}</span><span class="lang-name">${info.name}</span>`;
    }
    
    return `<span class="lang-badge">${lang.toUpperCase()}</span>`;
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

function deleteSelectedClass() {
    deleteClass();
}

// Modal zarządzania zestawem
let managingDeckId = null;

async function showDeckManageModal(deckId, deckTitle, isPublic, shareToken) {
    managingDeckId = deckId;
    
    // Utwórz modal jeśli nie istnieje
    let modal = document.getElementById('deckManageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'deckManageModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2 id="manageDeckTitle">Zarządzaj zestawem</h2>
                    <button class="close-btn" onclick="closeModal('deckManageModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="manage-tabs">
                        <button class="manage-tab active" data-tab="cards">Fiszki</button>
                        <button class="manage-tab" data-tab="classes">Klasy</button>
                        <button class="manage-tab" data-tab="sharing">Udostępnianie</button>
                    </div>
                    
                    <div class="manage-content" id="cardsContent">
                        <div class="cards-manager-header">
                            <button class="btn-primary btn-sm" onclick="showCreateCardModal()">+ Dodaj fiszkę</button>
                        </div>
                        <div id="manageCardsList" class="cards-list">
                            <p class="loading">Ładowanie fiszek...</p>
                        </div>
                    </div>
                    
                    <div class="manage-content" id="classesContent" style="display: none;">
                        <p class="manage-info">Przypisz zestaw do klas. Uczniowie w tych klasach będą mogli się z niego uczyć.</p>
                        <div id="manageClassSelection" class="class-selection">
                            <p class="text-muted">Ładowanie klas...</p>
                        </div>
                        <div class="modal-actions" style="margin-top: 1rem;">
                            <button class="btn-primary" onclick="saveClassAssignment()">Zapisz przypisania</button>
                        </div>
                    </div>
                    
                    <div class="manage-content" id="sharingContent" style="display: none;">
                        <div class="sharing-options">
                            <div class="sharing-option">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="manageIsPublic">
                                    Udostępnij społeczności
                                </label>
                                <p class="option-desc">Zestaw będzie widoczny dla wszystkich użytkowników.</p>
                            </div>
                            <div id="shareLinkSection" style="display: none;">
                                <label>Link do udostępnienia:</label>
                                <div class="share-link-row">
                                    <input type="text" id="manageShareLink" readonly>
                                    <button class="btn-secondary btn-sm" onclick="copyManageShareLink()">Kopiuj</button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-actions" style="margin-top: 1rem;">
                            <button class="btn-primary" onclick="saveSharingSettings()">Zapisz ustawienia</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer danger-zone">
                    <button class="btn-danger" onclick="deleteDeckFromModal()">Usuń zestaw</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Setup tabs
        modal.querySelectorAll('.manage-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                modal.querySelectorAll('.manage-tab').forEach(t => t.classList.remove('active'));
                modal.querySelectorAll('.manage-content').forEach(c => c.style.display = 'none');
                tab.classList.add('active');
                const contentId = tab.dataset.tab + 'Content';
                document.getElementById(contentId).style.display = 'block';
            });
        });
    }
    
    document.getElementById('manageDeckTitle').textContent = `Zarządzaj: ${deckTitle}`;
    
    // Ustaw public checkbox
    document.getElementById('manageIsPublic').checked = isPublic;
    
    // Ustaw link
    const shareLinkSection = document.getElementById('shareLinkSection');
    if (isPublic && shareToken) {
        shareLinkSection.style.display = 'block';
        document.getElementById('manageShareLink').value = `${window.location.origin}/public-deck?token=${shareToken}`;
    } else {
        shareLinkSection.style.display = 'none';
    }
    
    // Załaduj fiszki
    await loadManageCards(deckId);
    
    // Załaduj klasy
    await loadManageClasses(deckId);
    
    modal.style.display = 'flex';
}

async function loadManageCards(deckId) {
    const container = document.getElementById('manageCardsList');
    try {
        const result = await API.decks.cards(deckId);
        if (result.ok) {
            if (result.data.length === 0) {
                container.innerHTML = '<p class="no-data">Brak fiszek. Dodaj pierwszą!</p>';
            } else {
                container.innerHTML = result.data.map(c => `
                    <div class="card-item">
                        ${c.imagePath ? `<img src="${c.imagePath}" alt="" class="card-thumbnail">` : ''}
                        <span class="card-front">${escapeHtml(c.front)}</span>
                        <span class="separator">→</span>
                        <span class="card-back">${escapeHtml(c.back)}</span>
                        <button class="btn-sm btn-danger" onclick="deleteCard(${c.id})">✕</button>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        container.innerHTML = '<p class="error">Błąd ładowania fiszek</p>';
    }
}

async function loadManageClasses(deckId) {
    const container = document.getElementById('manageClassSelection');
    
    try {
        const deckResult = await API.decks.get(deckId);
        const currentClassIds = deckResult.ok && deckResult.data.classIds ? deckResult.data.classIds : [];
        
        if (teacherClasses.length === 0) {
            container.innerHTML = '<p class="text-muted">Nie masz jeszcze żadnych klas.</p>';
            return;
        }
        
        container.innerHTML = teacherClasses.map(c => `
            <label class="checkbox-label">
                <input type="checkbox" name="manageClassIds" value="${c.id}" 
                       ${currentClassIds.includes(c.id) ? 'checked' : ''}>
                ${getLanguageFlag(c.language)} ${escapeHtml(c.name)}
            </label>
        `).join('');
    } catch (error) {
        container.innerHTML = '<p class="error">Błąd ładowania klas</p>';
    }
}

async function saveClassAssignment() {
    if (!managingDeckId) return;
    
    const checkboxes = document.querySelectorAll('input[name="manageClassIds"]:checked');
    const classIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    try {
        const result = await API.decks.assignToClasses(managingDeckId, classIds);
        if (result.ok) {
            showToast('Przypisania zostały zapisane', 'success');
            await loadTeacherDecks();
        } else {
            showToast('Błąd: ' + (result.error?.message || 'Nie udało się zapisać'), 'error');
        }
    } catch (error) {
        showToast('Wystąpił błąd', 'error');
    }
}

async function saveSharingSettings() {
    if (!managingDeckId) return;
    
    const isPublic = document.getElementById('manageIsPublic').checked;
    
    try {
        const result = await API.put(`/api/decks/${managingDeckId}`, { isPublic });
        if (result.ok) {
            showToast(isPublic ? 'Zestaw został upubliczniony!' : 'Zestaw został ukryty.', 'success');
            closeModal('deckManageModal');
            await loadTeacherDecks();
        } else {
            showToast('Błąd: ' + (result.error?.message || 'Nie udało się zapisać'), 'error');
        }
    } catch (error) {
        showToast('Wystąpił błąd', 'error');
    }
}

function copyManageShareLink() {
    const input = document.getElementById('manageShareLink');
    navigator.clipboard.writeText(input.value).then(() => {
        showToast('Link skopiowany!', 'success');
    });
}

async function deleteCard(cardId) {
    if (!managingDeckId) return;
    
    showConfirmModal(
        '🗑️ Usuń fiszkę',
        'Czy na pewno chcesz usunąć tę fiszkę?',
        async () => {
            try {
                const result = await API.delete(`/api/decks/${managingDeckId}/cards/${cardId}`);
                if (result.ok) {
                    await loadManageCards(managingDeckId);
                    await loadTeacherDecks();
                    showToast('Fiszka została usunięta', 'success');
                } else {
                    showToast('Błąd usuwania fiszki', 'error');
                }
            } catch (error) {
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

function deleteDeckFromModal() {
    if (!managingDeckId) return;
    
    showConfirmModal(
        '🗑️ Usuń zestaw',
        'Czy na pewno chcesz usunąć ten zestaw fiszek? Tej operacji nie można cofnąć.',
        async () => {
            try {
                const result = await API.decks.delete(managingDeckId);
                if (result.ok) {
                    closeModal('deckManageModal');
                    await loadTeacherDecks();
                    showToast('Zestaw został usunięty', 'success');
                } else {
                    showToast('Błąd: ' + (result.error?.message || 'Nie udało się usunąć'), 'error');
                }
            } catch (error) {
                showToast('Wystąpił błąd', 'error');
            }
        }
    );
}

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
