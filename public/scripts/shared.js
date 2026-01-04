/**
 * Shared module - wspólne funkcje i szablony dla całej aplikacji
 */

// ==================== POMOCNICZE FUNKCJE ====================

/**
 * Escape HTML - zapobiega XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Obsługa błędu ładowania obrazka - zapobiega nieskończonej pętli
 */
function handleImageError(img) {
    if (img.dataset.errorHandled) return;
    img.dataset.errorHandled = 'true';
    img.onerror = null;
    
    const placeholder = document.createElement('div');
    placeholder.className = 'image-placeholder';
    placeholder.innerHTML = '📚';
    img.parentNode.replaceChild(placeholder, img);
}

// ==================== FLAGI JĘZYKÓW ====================

/**
 * Mapowanie kodów języków na kody flag
 */
const LANGUAGE_TO_FLAG = {
    'de': 'de', 'en': 'gb', 'es': 'es', 'fr': 'fr', 'it': 'it',
    'ru': 'ru', 'pl': 'pl', 'ja': 'jp', 'zh': 'cn', 'pt': 'pt',
    'nl': 'nl', 'sv': 'se', 'no': 'no', 'da': 'dk', 'fi': 'fi',
    'cs': 'cz', 'sk': 'sk', 'uk': 'ua', 'el': 'gr', 'tr': 'tr',
    'ar': 'sa', 'ko': 'kr', 'hi': 'in'
};

/**
 * Pełne nazwy języków
 */
const LANGUAGE_NAMES = {
    'de': 'Niemiecki', 'en': 'Angielski', 'es': 'Hiszpański', 'fr': 'Francuski',
    'it': 'Włoski', 'ru': 'Rosyjski', 'pl': 'Polski', 'ja': 'Japoński',
    'zh': 'Chiński', 'pt': 'Portugalski', 'nl': 'Niderlandzki', 'sv': 'Szwedzki',
    'no': 'Norweski', 'da': 'Duński', 'fi': 'Fiński', 'cs': 'Czeski',
    'sk': 'Słowacki', 'uk': 'Ukraiński', 'el': 'Grecki', 'tr': 'Turecki',
    'ar': 'Arabski', 'ko': 'Koreański', 'hi': 'Hindi'
};

/**
 * Zwraca URL do lokalnej flagi SVG
 */
function getLanguageFlagUrl(language) {
    if (!language) return '/public/images/flags/unknown.svg';
    const lang = language.toLowerCase();
    const flagCode = LANGUAGE_TO_FLAG[lang] || 'unknown';
    return `/public/images/flags/${flagCode}.svg`;
}

/**
 * Zwraca HTML z flagą (img) i opcjonalnie nazwą języka
 */
function getLanguageFlagHtml(language, showName = false) {
    if (!language) {
        return '<img class="flag" src="/public/images/flags/unknown.svg" alt="unknown">';
    }
    const lang = language.toLowerCase();
    const flagCode = LANGUAGE_TO_FLAG[lang] || 'unknown';
    const langName = LANGUAGE_NAMES[lang] || lang.toUpperCase();
    
    let html = `<img class="flag" src="/public/images/flags/${flagCode}.svg" alt="${langName}" title="${langName}">`;
    if (showName) {
        html += `<span class="lang-name">${langName}</span>`;
    }
    return html;
}

/**
 * Zwraca pełną nazwę języka
 */
function getLanguageName(language) {
    if (!language) return '';
    return LANGUAGE_NAMES[language.toLowerCase()] || language.toUpperCase();
}

// ==================== POZIOMY TRUDNOŚCI ====================

const LEVEL_LABELS = {
    'beginner': 'Początkujący',
    'intermediate': 'Średniozaawansowany',
    'advanced': 'Zaawansowany'
};

function getLevelLabel(level) {
    return LEVEL_LABELS[level] || level || 'Nieznany';
}

// ==================== SZABLONY KART ====================

/**
 * Tworzy HTML karty zestawu fiszek (deck card)
 * @param {Object} deck - obiekt zestawu
 * @param {Object} options - opcje { showTeacher, showClass, showActions, actionButtons }
 */
function createDeckCardHtml(deck, options = {}) {
    const {
        showTeacher = true,
        showClass = false,
        showActions = true,
        actionButtons = null,
        showRating = false,
        showViews = false
    } = options;
    
    const imageUrl = deck.imageUrl || deck.image_path || '';
    const imageHtml = imageUrl 
        ? `<img src="${imageUrl}" alt="${escapeHtml(deck.title)}" onerror="handleImageError(this)">`
        : '<div class="image-placeholder">📚</div>';
    
    let metaHtml = '';
    if (showTeacher && deck.teacherName) {
        metaHtml += `<span class="meta-item">👤 ${escapeHtml(deck.teacherName)}</span>`;
    }
    if (showClass && deck.className) {
        metaHtml += `<span class="meta-item">📁 ${escapeHtml(deck.className)}</span>`;
    }
    metaHtml += `<span class="meta-item">📚 ${deck.cardCount || 0} fiszek</span>`;
    
    let actionsHtml = '';
    if (showActions) {
        if (actionButtons) {
            actionsHtml = actionButtons;
        } else {
            actionsHtml = `<a href="/study?deckId=${deck.id}" class="btn-primary btn-sm">Ucz się</a>`;
        }
    }
    
    let extraHtml = '';
    if (showRating && deck.averageRating !== undefined) {
        extraHtml += `<div class="deck-rating">${getStarsHtml(deck.averageRating)} (${deck.ratingsCount || 0})</div>`;
    }
    if (showViews && deck.viewsCount !== undefined) {
        extraHtml += `<span class="deck-views">👁 ${deck.viewsCount}</span>`;
    }
    
    return `
        <div class="deck-card" data-deck-id="${deck.id}">
            <div class="deck-card-image">${imageHtml}</div>
            <div class="deck-card-content">
                <h3 class="deck-card-title">${escapeHtml(deck.title)}</h3>
                <p class="deck-card-desc">${escapeHtml(deck.description) || 'Brak opisu'}</p>
                <div class="deck-card-meta">${metaHtml}</div>
                <div class="deck-card-footer">
                    <span class="level level-${deck.level}">${getLevelLabel(deck.level)}</span>
                    ${extraHtml}
                </div>
            </div>
            ${showActions ? `<div class="deck-card-actions">${actionsHtml}</div>` : ''}
        </div>
    `;
}

/**
 * Tworzy HTML karty klasy
 */
function createClassCardHtml(classData, options = {}) {
    const { showTeacher = true, onClick = null } = options;
    
    const flagHtml = getLanguageFlagHtml(classData.language);
    
    return `
        <div class="class-card" data-class-id="${classData.id}" ${onClick ? `onclick="${onClick}"` : ''}>
            <div class="class-card-header">
                ${flagHtml}
                <h3>${escapeHtml(classData.name)}</h3>
            </div>
            ${showTeacher && classData.teacherName ? `<p class="class-teacher">${escapeHtml(classData.teacherName)}</p>` : ''}
            ${classData.joinCode ? `<p class="join-code">Kod: <strong>${classData.joinCode}</strong></p>` : ''}
        </div>
    `;
}

/**
 * Zwraca HTML gwiazdek dla oceny
 */
function getStarsHtml(rating) {
    const fullStars = Math.floor(rating || 0);
    const halfStar = (rating || 0) % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
    
    let html = '<span class="stars">';
    html += '★'.repeat(fullStars);
    if (halfStar) html += '½';
    html += '☆'.repeat(emptyStars);
    html += '</span>';
    
    return html;
}

// ==================== MODAL HELPER ====================

/**
 * Zamyka modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Otwiera modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

// ==================== TOAST NOTIFICATIONS ====================

/**
 * Pokazuje powiadomienie toast
 */
function showToast(message, type = 'info', duration = 3000) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('toast-fade-out');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
