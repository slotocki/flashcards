/**
 * Community Module - Obsługa publicznych zestawów fiszek
 * Wykorzystuje ustandaryzowane szablony z shared.js (createDeckCardHtml)
 */

// Stan aplikacji
let currentPage = 1;
let totalPages = 1;
let currentSearch = '';
let currentSort = 'popular';
let currentDeckId = null;

// Inicjalizacja po załadowaniu strony
document.addEventListener('DOMContentLoaded', () => {
    initCommunity();
});

/**
 * Inicjalizacja modułu społeczności
 */
function initCommunity() {
    loadSubscribedDecks();
    loadPublicDecks();
    setupEventListeners();
}

/**
 * Konfiguracja listenerów zdarzeń
 */
function setupEventListeners() {
    // Wyszukiwanie
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');
    
    searchBtn?.addEventListener('click', () => {
        currentSearch = searchInput.value.trim();
        currentPage = 1;
        loadPublicDecks();
    });
    
    searchInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            currentSearch = searchInput.value.trim();
            currentPage = 1;
            loadPublicDecks();
        }
    });
    
    // Sortowanie
    const sortSelect = document.getElementById('sortSelect');
    sortSelect?.addEventListener('change', () => {
        currentSort = sortSelect.value;
        currentPage = 1;
        loadPublicDecks();
    });
    
    // Paginacja
    document.getElementById('prevPage')?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadPublicDecks();
        }
    });
    
    document.getElementById('nextPage')?.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadPublicDecks();
        }
    });
    
    // Modal
    const modal = document.getElementById('deckDetailModal');
    modal?.querySelector('.close-btn')?.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Kopiowanie linku
    document.getElementById('copyShareLink')?.addEventListener('click', () => {
        const input = document.getElementById('shareLinkInput');
        input.select();
        document.execCommand('copy');
        showToast('Link skopiowany do schowka!');
    });
    
    // Subskrypcja
    document.getElementById('subscribeBtn')?.addEventListener('click', toggleSubscription);
    
    // Nauka decku
    document.getElementById('studyDeckBtn')?.addEventListener('click', () => {
        if (currentDeckId) {
            window.location.href = `/study?deckId=${currentDeckId}`;
        }
    });
    
    // Ocena gwiazdkowa
    setupStarRating();
}

/**
 * Ładuje subskrybowane decki
 */
async function loadSubscribedDecks() {
    const container = document.querySelector('.subscribed-decks-list');
    if (!container) return;
    
    try {
        const response = await API.get('/api/community/subscribed');
        
        if (response.ok && response.data.decks) {
            renderSubscribedDecks(response.data.decks, container);
        } else {
            container.innerHTML = '<p class="no-data">Nie masz jeszcze subskrybowanych zestawów.</p>';
        }
    } catch (error) {
        console.error('Błąd ładowania subskrypcji:', error);
        container.innerHTML = '<p class="error">Nie udało się załadować subskrypcji.</p>';
    }
}

/**
 * Renderuje subskrybowane decki
 */
function renderSubscribedDecks(decks, container) {
    if (!decks.length) {
        container.innerHTML = '<p class="no-data">Nie masz jeszcze subskrybowanych zestawów. Przeglądaj społeczność i dodaj interesujące Cię zestawy!</p>';
        return;
    }
    
    // Używamy ustandaryzowanego szablonu z shared.js
    container.innerHTML = `<div class="deck-cards-grid">${decks.map(deck => {
        deck.isSubscribed = true; // oznacz jako subskrybowany
        return createDeckCardHtml(deck, {
            showTeacher: true,
            showRating: true,
            showViews: true,
            showSubscribedBadge: true
        });
    }).join('')}</div>`;
    
    // Dodaj event listenery
    container.querySelectorAll('.deck-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => {
            const deckId = parseInt(card.dataset.deckId);
            openDeckDetails(deckId);
        });
    });
}

/**
 * Ładuje publiczne decki
 */
async function loadPublicDecks() {
    const container = document.querySelector('.public-decks-list');
    if (!container) return;
    
    container.innerHTML = '<p class="loading">Ładowanie zestawów...</p>';
    
    try {
        const params = new URLSearchParams({
            search: currentSearch,
            sort: currentSort,
            page: currentPage,
            limit: 12
        });
        
        const response = await API.get(`/api/community/decks?${params}`);
        
        if (response.ok && response.data) {
            const { decks, pagination } = response.data;
            totalPages = pagination.totalPages;
            
            renderPublicDecks(decks, container);
            updatePagination(pagination);
        } else {
            container.innerHTML = '<p class="error">Nie udało się załadować zestawów.</p>';
        }
    } catch (error) {
        console.error('Błąd ładowania decków:', error);
        container.innerHTML = '<p class="error">Wystąpił błąd podczas ładowania.</p>';
    }
}

/**
 * Renderuje publiczne decki
 */
function renderPublicDecks(decks, container) {
    if (!decks.length) {
        container.innerHTML = currentSearch 
            ? '<p class="no-data">Nie znaleziono zestawów pasujących do wyszukiwania.</p>'
            : '<p class="no-data">Brak publicznych zestawów do wyświetlenia.</p>';
        return;
    }
    
    // Używamy ustandaryzowanego szablonu z shared.js
    container.innerHTML = `<div class="deck-cards-grid">${decks.map(deck => 
        createDeckCardHtml(deck, {
            showTeacher: true,
            showRating: true,
            showViews: true
        })
    ).join('')}</div>`;
    
    // Dodaj event listenery
    container.querySelectorAll('.deck-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => {
            const deckId = parseInt(card.dataset.deckId);
            openDeckDetails(deckId);
        });
    });
}

// Stara funkcja createDeckCard została zastąpiona przez createDeckCardHtml z shared.js

/**
 * Aktualizuje paginację
 */
function updatePagination(pagination) {
    const paginationEl = document.getElementById('pagination');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    const pageInfo = document.getElementById('pageInfo');
    
    if (pagination.totalPages <= 1) {
        paginationEl.style.display = 'none';
        return;
    }
    
    paginationEl.style.display = 'flex';
    prevBtn.disabled = pagination.page <= 1;
    nextBtn.disabled = pagination.page >= pagination.totalPages;
    pageInfo.textContent = `Strona ${pagination.page} z ${pagination.totalPages}`;
}

/**
 * Otwiera szczegóły decku
 */
async function openDeckDetails(deckId) {
    currentDeckId = deckId;
    const modal = document.getElementById('deckDetailModal');
    
    try {
        const response = await API.get(`/api/community/deck/${deckId}`);
        
        if (response.ok) {
            const deck = response.data;
            renderDeckDetails(deck);
            modal.style.display = 'flex';
        } else {
            showToast('Nie udało się załadować szczegółów zestawu.', 'error');
        }
    } catch (error) {
        console.error('Błąd ładowania szczegółów:', error);
        showToast('Wystąpił błąd.', 'error');
    }
}

/**
 * Renderuje szczegóły decku w modalu
 */
function renderDeckDetails(deck) {
    document.getElementById('modalDeckTitle').textContent = deck.title;
    document.getElementById('modalDeckTeacher').textContent = deck.teacherName || 'Nieznany';
    document.getElementById('modalDeckClass').textContent = deck.className || '-';
    document.getElementById('modalDeckLevel').textContent = getLevelLabel(deck.level);
    document.getElementById('modalDeckCardCount').textContent = deck.cardCount || 0;
    document.getElementById('modalDeckViews').textContent = deck.viewsCount || 0;
    document.getElementById('modalDeckDescription').textContent = deck.description || 'Brak opisu';
    
    const imageEl = document.getElementById('modalDeckImage');
    imageEl.dataset.errorHandled = '';  // Reset flagi przy nowym obrazku
    imageEl.src = deck.imageUrl || '/public/images/default-deck.png';
    imageEl.onerror = function() { 
        if (this.dataset.errorHandled) return;
        this.dataset.errorHandled = 'true';
        this.onerror = null;
        this.style.display = 'none';
    };
    
    // Ocena
    document.getElementById('modalAverageRating').textContent = (deck.averageRating || 0).toFixed(1);
    document.getElementById('modalStars').innerHTML = getStarsHtml(deck.averageRating || 0);
    document.getElementById('modalRatingsCount').textContent = deck.ratingsCount || 0;
    
    // Ocena użytkownika
    updateUserRatingDisplay(deck.userRating);
    
    // Link do udostępniania
    if (deck.shareToken) {
        const shareUrl = `${window.location.origin}/public-deck?token=${deck.shareToken}`;
        document.getElementById('shareLinkInput').value = shareUrl;
    }
    
    // Przycisk subskrypcji
    const subscribeBtn = document.getElementById('subscribeBtn');
    if (deck.isSubscribed) {
        subscribeBtn.textContent = 'Anuluj subskrypcję';
        subscribeBtn.classList.add('subscribed');
    } else {
        subscribeBtn.textContent = 'Subskrybuj';
        subscribeBtn.classList.remove('subscribed');
    }
    
    // Podgląd fiszek
    renderCardsPreview(deck.cards || []);
}

/**
 * Renderuje podgląd fiszek
 */
function renderCardsPreview(cards) {
    const container = document.getElementById('cardsPreviewList');
    
    if (!cards.length) {
        container.innerHTML = '<p class="no-data">Ten zestaw nie zawiera jeszcze fiszek.</p>';
        return;
    }
    
    // Pokaż maksymalnie 5 fiszek
    const previewCards = cards.slice(0, 5);
    
    container.innerHTML = previewCards.map((card, index) => `
        <div class="card-preview-item">
            <span class="card-number">${index + 1}.</span>
            <span class="card-front">${escapeHtml(card.front)}</span>
            <span class="card-arrow">→</span>
            <span class="card-back">${escapeHtml(card.back)}</span>
        </div>
    `).join('');
    
    if (cards.length > 5) {
        container.innerHTML += `<p class="more-cards">...i ${cards.length - 5} więcej fiszek</p>`;
    }
}

/**
 * Konfiguracja oceny gwiazdkowej
 */
function setupStarRating() {
    const starContainer = document.getElementById('userStarRating');
    if (!starContainer) return;
    
    const stars = starContainer.querySelectorAll('span');
    
    stars.forEach(star => {
        star.addEventListener('mouseenter', () => {
            const rating = parseInt(star.dataset.rating);
            highlightStars(stars, rating);
        });
        
        star.addEventListener('mouseleave', () => {
            // Przywróć aktualną ocenę
            const currentRating = parseInt(starContainer.dataset.currentRating) || 0;
            highlightStars(stars, currentRating);
        });
        
        star.addEventListener('click', async () => {
            const rating = parseInt(star.dataset.rating);
            await submitRating(rating);
        });
    });
}

/**
 * Podświetla gwiazdki
 */
function highlightStars(stars, rating) {
    stars.forEach(star => {
        const starRating = parseInt(star.dataset.rating);
        star.textContent = starRating <= rating ? '★' : '☆';
        star.classList.toggle('active', starRating <= rating);
    });
}

/**
 * Aktualizuje wyświetlanie oceny użytkownika
 */
function updateUserRatingDisplay(rating) {
    const starContainer = document.getElementById('userStarRating');
    if (!starContainer) return;
    
    starContainer.dataset.currentRating = rating || 0;
    const stars = starContainer.querySelectorAll('span');
    highlightStars(stars, rating || 0);
}

/**
 * Wysyła ocenę
 */
async function submitRating(rating) {
    if (!currentDeckId) return;
    
    try {
        const response = await API.post(`/api/community/deck/${currentDeckId}/rate`, { rating });
        
        if (response.ok) {
            showToast('Ocena zapisana!');
            document.getElementById('modalAverageRating').textContent = response.data.averageRating.toFixed(1);
            document.getElementById('modalStars').innerHTML = getStarsHtml(response.data.averageRating);
            document.getElementById('modalRatingsCount').textContent = response.data.ratingsCount;
            updateUserRatingDisplay(rating);
            
            // Odśwież listę
            loadPublicDecks();
            loadSubscribedDecks();
        } else {
            showToast(response.error?.message || 'Nie udało się zapisać oceny.', 'error');
        }
    } catch (error) {
        console.error('Błąd zapisywania oceny:', error);
        showToast('Wystąpił błąd.', 'error');
    }
}

/**
 * Przełącza subskrypcję
 */
async function toggleSubscription() {
    if (!currentDeckId) return;
    
    const subscribeBtn = document.getElementById('subscribeBtn');
    const isSubscribed = subscribeBtn.classList.contains('subscribed');
    
    try {
        let response;
        if (isSubscribed) {
            response = await API.delete(`/api/community/deck/${currentDeckId}/unsubscribe`);
        } else {
            response = await API.post(`/api/community/deck/${currentDeckId}/subscribe`);
        }
        
        if (response.ok) {
            if (isSubscribed) {
                subscribeBtn.textContent = 'Subskrybuj';
                subscribeBtn.classList.remove('subscribed');
                showToast('Usunięto z subskrypcji.');
            } else {
                subscribeBtn.textContent = 'Anuluj subskrypcję';
                subscribeBtn.classList.add('subscribed');
                showToast('Dodano do subskrypcji!');
            }
            
            // Odśwież listy
            loadSubscribedDecks();
            loadPublicDecks();
        } else {
            showToast(response.error?.message || 'Operacja nie powiodła się.', 'error');
        }
    } catch (error) {
        console.error('Błąd subskrypcji:', error);
        showToast('Wystąpił błąd.', 'error');
    }
}

// ================== Funkcje pomocnicze ==================
// Korzystamy z funkcji z shared.js: getLanguageFlagHtml, getStarsHtml, getLevelLabel, escapeHtml, showToast

/**
 * Zwraca flagę dla języka - alias dla kompatybilności
 */
function getLanguageFlag(language) {
    return getLanguageFlagHtml(language, false);
}

// getLevelBadge i getLevelLabel są dostępne z shared.js

// Sprawdź czy jest parametr share w URL
const urlParams = new URLSearchParams(window.location.search);
const shareToken = urlParams.get('share');
if (shareToken) {
    // Załaduj deck z tokenu
    (async () => {
        try {
            const response = await API.get(`/api/community/share/${shareToken}`);
            if (response.ok) {
                currentDeckId = response.data.id;
                renderDeckDetails(response.data);
                document.getElementById('deckDetailModal').style.display = 'flex';
            }
        } catch (error) {
            console.error('Błąd ładowania udostępnionego decku:', error);
        }
    })();
}
