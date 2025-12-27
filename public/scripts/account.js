/**
 * Account module - zarządzanie kontem
 */

document.addEventListener('DOMContentLoaded', () => {
    initAccountPage();
});

async function initAccountPage() {
    await loadAccountInfo();
    setupAccountForms();
}

/**
 * Ładuje dane konta
 */
async function loadAccountInfo() {
    try {
        const result = await API.auth.me();
        
        if (result.ok) {
            const user = result.data;
            document.getElementById('accountEmail').textContent = user.email;
            document.getElementById('accountRole').textContent = getRoleLabel(user.role);
            document.getElementById('accountStatus').textContent = user.enabled ? 'Aktywne' : 'Nieaktywne';
            
            // Wypełnij formularz profilu
            document.getElementById('firstname').value = user.firstname || '';
            document.getElementById('lastname').value = user.lastname || '';
            document.getElementById('bio').value = user.bio || '';
        }
    } catch (error) {
        console.error('Error loading account info:', error);
    }
}

/**
 * Formularze
 */
function setupAccountForms() {
    // Formularz profilu
    document.getElementById('profileForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveProfile(e.target);
    });
    
    // Formularz hasła
    document.getElementById('passwordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await changePassword(e.target);
    });
}

/**
 * Zapisuje profil
 */
async function saveProfile(form) {
    try {
        const result = await API.put('/api/auth/profile', {
            firstname: form.firstname.value,
            lastname: form.lastname.value,
            bio: form.bio.value
        });
        
        if (result.ok) {
            alert('Profil został zaktualizowany');
        } else {
            alert('Błąd: ' + (result.error?.message || 'Nie udało się zaktualizować profilu'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd');
    }
}

/**
 * Zmienia hasło
 */
async function changePassword(form) {
    const newPassword = form.newPassword.value;
    const confirmPassword = form.confirmPassword.value;
    
    if (newPassword !== confirmPassword) {
        alert('Nowe hasła nie są identyczne');
        return;
    }
    
    if (newPassword.length < 6) {
        alert('Nowe hasło musi mieć co najmniej 6 znaków');
        return;
    }
    
    try {
        const result = await API.put('/api/auth/password', {
            currentPassword: form.currentPassword.value,
            newPassword: newPassword
        });
        
        if (result.ok) {
            alert('Hasło zostało zmienione');
            form.reset();
        } else {
            alert('Błąd: ' + (result.error?.message || 'Nie udało się zmienić hasła'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd');
    }
}

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
