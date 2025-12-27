/**
 * Admin module - panel administracyjny
 */

document.addEventListener('DOMContentLoaded', () => {
    initAdminPanel();
});

async function initAdminPanel() {
    setupAdminTabs();
    await loadUsers();
}

/**
 * Zakładki panelu admina
 */
function setupAdminTabs() {
    document.querySelectorAll('.admin-tabs .tab').forEach(tab => {
        tab.addEventListener('click', async () => {
            document.querySelectorAll('.admin-tabs .tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.admin-tab-content').forEach(c => c.style.display = 'none');
            
            tab.classList.add('active');
            const tabId = tab.dataset.tab + 'Tab';
            document.getElementById(tabId).style.display = 'block';
            
            if (tab.dataset.tab === 'users') {
                await loadUsers();
            } else if (tab.dataset.tab === 'classes') {
                await loadAllClasses();
            }
        });
    });
}

/**
 * Ładuje listę użytkowników
 */
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="6" class="loading">Ładowanie...</td></tr>';
    
    try {
        const result = await API.get('/api/admin/users');
        
        if (result.ok) {
            renderUsers(result.data);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="error">Błąd ładowania użytkowników</td></tr>';
        }
    } catch (error) {
        console.error('Error loading users:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="error">Błąd ładowania</td></tr>';
    }
}

function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="no-data">Brak użytkowników</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(u => `
        <tr>
            <td>${u.id}</td>
            <td>${escapeHtml(u.firstname)} ${escapeHtml(u.lastname)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="badge badge-${u.role}">${getRoleLabel(u.role)}</span></td>
            <td><span class="status-${u.enabled ? 'active' : 'inactive'}">${u.enabled ? 'Aktywny' : 'Nieaktywny'}</span></td>
            <td>
                <button class="btn-sm btn-secondary" onclick="showRoleModal(${u.id}, '${u.role}')">Zmień rolę</button>
                <button class="btn-sm ${u.enabled ? 'btn-danger' : 'btn-primary'}" onclick="toggleUserStatus(${u.id}, ${!u.enabled})">
                    ${u.enabled ? 'Dezaktywuj' : 'Aktywuj'}
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Pokazuje modal zmiany roli
 */
function showRoleModal(userId, currentRole) {
    const modal = document.getElementById('changeRoleModal');
    const select = document.getElementById('newRole');
    document.getElementById('userId').value = userId;
    select.value = currentRole;
    modal.style.display = 'flex';
}

/**
 * Zmienia rolę użytkownika
 */
async function changeRole() {
    const userId = document.getElementById('userId').value;
    const newRole = document.getElementById('newRole').value;
    
    try {
        const result = await API.put(`/api/admin/users/${userId}/role`, { role: newRole });
        
        if (result.ok) {
            closeModal('changeRoleModal');
            await loadUsers();
        } else {
            alert('Błąd: ' + (result.error?.message || 'Nie udało się zmienić roli'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd');
    }
}

/**
 * Przełącza status użytkownika
 */
async function toggleUserStatus(userId, enabled) {
    const action = enabled ? 'aktywować' : 'dezaktywować';
    if (!confirm(`Czy na pewno chcesz ${action} tego użytkownika?`)) {
        return;
    }
    
    try {
        const result = await API.put(`/api/admin/users/${userId}/status`, { enabled: enabled });
        
        if (result.ok) {
            await loadUsers();
        } else {
            alert('Błąd: ' + (result.error?.message || 'Nie udało się zmienić statusu'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd');
    }
}

/**
 * Ładuje listę wszystkich klas
 */
async function loadAllClasses() {
    const tbody = document.getElementById('classesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="5" class="loading">Ładowanie...</td></tr>';
    
    try {
        const result = await API.get('/api/admin/classes');
        
        if (result.ok) {
            renderAllClasses(result.data);
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="error">Błąd ładowania klas</td></tr>';
        }
    } catch (error) {
        console.error('Error loading classes:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="error">Błąd ładowania</td></tr>';
    }
}

function renderAllClasses(classes) {
    const tbody = document.getElementById('classesTableBody');
    
    if (classes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="no-data">Brak klas</td></tr>';
        return;
    }
    
    tbody.innerHTML = classes.map(c => `
        <tr>
            <td>${c.id}</td>
            <td>${escapeHtml(c.name)}</td>
            <td>${escapeHtml(c.ownerName || 'Nieznany')}</td>
            <td><code>${c.joinCode}</code></td>
            <td>
                <button class="btn-sm btn-danger" onclick="deleteClassAdmin(${c.id})">Usuń</button>
            </td>
        </tr>
    `).join('');
}

/**
 * Usuwa klasę jako admin
 */
async function deleteClassAdmin(classId) {
    if (!confirm('Czy na pewno chcesz usunąć tę klasę? Wszystkie decki, fiszki i zadania zostaną usunięte!')) {
        return;
    }
    
    try {
        const result = await API.delete(`/api/classes/${classId}`);
        
        if (result.ok) {
            await loadAllClasses();
            alert('Klasa została usunięta');
        } else {
            alert('Błąd: ' + (result.error?.message || 'Nie udało się usunąć klasy'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd');
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
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
function getRoleLabel(role) {
    const labels = {
        'admin': 'Administrator',
        'teacher': 'Nauczyciel',
        'student': 'Uczeń'
    };
    return labels[role] || role;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
