/**
 * Main.js - shared utilities
 * Logout function for all pages
 */

async function logout() {
    try {
        await API.auth.logout();
        window.location.href = '/login';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = '/login';
    }
}

/**
 * Custom confirmation modal instead of browser's confirm()
 */
function showConfirmModal(title, message, onConfirm, onCancel) {
    // Remove existing modal if any
    const existing = document.getElementById('confirmModal');
    if (existing) existing.remove();
    
    const modal = document.createElement('div');
    modal.id = 'confirmModal';
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h2>${title}</h2>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="confirmCancel">Anuluj</button>
                <button class="btn-primary" id="confirmOk">Potwierdź</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('confirmOk').addEventListener('click', () => {
        modal.remove();
        if (onConfirm) onConfirm();
    });
    
    document.getElementById('confirmCancel').addEventListener('click', () => {
        modal.remove();
        if (onCancel) onCancel();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
            if (onCancel) onCancel();
        }
    });
}

/**
 * Toast notification
 */
function showToast(message, type = 'success') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}