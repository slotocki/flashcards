/**
 * Auth module - obsługa logowania i rejestracji
 */
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.querySelector('.login-form');
    const registerForm = document.querySelector('.register-form');
    
    // Obsługa logowania
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const errorDiv = document.querySelector('.error-message');
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            
            // Walidacja
            if (!email || !password) {
                showError(errorDiv, 'Wypełnij wszystkie pola');
                return;
            }
            
            // Disable button podczas ładowania
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logowanie...';
            
            try {
                const result = await API.auth.login(email, password);
                
                if (result.ok) {
                    // Sukces - przekieruj do dashboard
                    window.location.href = '/dashboard';
                } else {
                    showError(errorDiv, result.error?.message || 'Błąd logowania');
                }
            } catch (error) {
                console.error('Login error:', error);
                showError(errorDiv, 'Wystąpił błąd. Spróbuj ponownie.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Zaloguj';
            }
        });
    }
    
    // Obsługa rejestracji
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const role = document.querySelector('input[name="role"]:checked')?.value || 'student';
            const errorDiv = document.querySelector('.error-message');
            const submitBtn = registerForm.querySelector('button[type="submit"]');
            
            // Walidacja
            if (!firstName || !lastName || !email || !password || !confirmPassword) {
                showError(errorDiv, 'Wypełnij wszystkie pola');
                return;
            }
            
            if (password !== confirmPassword) {
                showError(errorDiv, 'Hasła nie są identyczne');
                return;
            }
            
            if (password.length < 8) {
                showError(errorDiv, 'Hasło musi mieć minimum 8 znaków');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Rejestracja...';
            
            try {
                const result = await API.auth.register({
                    firstName,
                    lastName,
                    email,
                    password,
                    confirmPassword,
                    role
                });
                
                if (result.ok) {
                    // Sukces - przekieruj do logowania
                    window.location.href = '/login?registered=1';
                } else {
                    showError(errorDiv, result.error?.message || 'Błąd rejestracji');
                }
            } catch (error) {
                console.error('Register error:', error);
                showError(errorDiv, 'Wystąpił błąd. Spróbuj ponownie.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Zarejestruj';
            }
        });
    }
    
    // Sprawdź czy użytkownik jest zalogowany i pokaż odpowiedni UI
    checkAuthStatus();
});

/**
 * Wyświetla komunikat błędu
 */
function showError(element, message) {
    if (!element) {
        // Utwórz element jeśli nie istnieje
        element = document.createElement('div');
        element.className = 'error-message';
        const form = document.querySelector('form');
        if (form) {
            form.insertBefore(element, form.firstChild);
        }
    }
    element.textContent = message;
    element.style.display = 'block';
}

/**
 * Ukrywa komunikat błędu
 */
function hideError(element) {
    if (element) {
        element.style.display = 'none';
    }
}

/**
 * Sprawdza status autoryzacji
 */
async function checkAuthStatus() {
    try {
        const result = await API.auth.me();
        
        if (result.ok) {
            // Użytkownik zalogowany
            window.currentUser = result.data;
            document.dispatchEvent(new CustomEvent('userLoaded', { detail: result.data }));
        }
    } catch (error) {
        // Użytkownik nie zalogowany - to ok na stronach publicznych
        console.log('User not authenticated');
    }
}

/**
 * Wylogowanie
 */
async function logout() {
    try {
        await API.auth.logout();
        window.location.href = '/login';
    } catch (error) {
        console.error('Logout error:', error);
        // Mimo błędu, przekieruj do logowania
        window.location.href = '/login';
    }
}
