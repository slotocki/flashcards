/**
 * Progress module - widok progresu użytkownika
 */
document.addEventListener('DOMContentLoaded', () => {
    initProgress();
});

/**
 * Inicjalizacja widoku progresu
 */
async function initProgress() {
    await loadUserProgress();
}

/**
 * Ładuje progres użytkownika
 */
async function loadUserProgress() {
    const container = document.querySelector('.progress-container, main');
    if (!container) return;
    
    try {
        const result = await API.progress.stats();
        
        if (result.ok) {
            renderProgress(result.data);
        } else {
            container.innerHTML = '<p class="error">Nie udało się załadować progresu</p>';
        }
    } catch (error) {
        console.error('Error loading progress:', error);
        container.innerHTML = '<p class="error">Błąd ładowania progresu</p>';
    }
}

/**
 * Renderuje progres
 */
function renderProgress(data) {
    const container = document.querySelector('.progress-container, main');
    if (!container) return;
    
    // Ogólne statystyki
    const overallHtml = `
        <section class="overall-stats">
            <h2>Twoje statystyki</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number">${data.overall.totalStudied}</span>
                    <span class="stat-label">Przejrzanych fiszek</span>
                </div>
                <div class="stat-card stat-known">
                    <span class="stat-number">${data.overall.totalKnown}</span>
                    <span class="stat-label">Opanowanych</span>
                </div>
                <div class="stat-card stat-learning">
                    <span class="stat-number">${data.overall.totalLearning}</span>
                    <span class="stat-label">W nauce</span>
                </div>
            </div>
        </section>
    `;
    
    // Sortuj zestawy od najmniej ukończonych (rosnąco wg procentu)
    const sortedDecks = [...data.byDeck].sort((a, b) => a.progress - b.progress);
    
    // Progres po deckach
    let decksHtml = '<section class="decks-progress"><h2>Progres w zestawach</h2>';
    
    if (sortedDecks.length === 0) {
        decksHtml += '<p class="no-data">Nie masz jeszcze żadnego progresu. Rozpocznij naukę!</p>';
    } else {
        decksHtml += '<div class="deck-progress-list">';
        
        sortedDecks.forEach(deck => {
            decksHtml += `
                <div class="deck-progress-item">
                    <div class="deck-progress-header">
                        <div class="deck-info">
                            <h3>${escapeHtml(deck.deckTitle)}</h3>
                            <p class="class-name">${escapeHtml(deck.className)}</p>
                        </div>
                        <div class="deck-percentage">${deck.progress}%</div>
                    </div>
                    <div class="deck-progress-bar">
                        <div class="deck-progress-fill" style="width: ${deck.progress}%"></div>
                    </div>
                    <div class="deck-progress-footer">
                        <div class="deck-stats">
                            <span class="deck-stat known"><span class="dot"></span>${deck.knownCards}/${deck.totalCards} opanowanych</span>
                        </div>
                        <div class="deck-actions">
                            <a href="/study?deckId=${deck.deckId}">Ucz się</a>
                        </div>
                    </div>
                </div>
            `;
        });
        
        decksHtml += '</div>';
    }
    
    decksHtml += '</section>';
    
    container.innerHTML = overallHtml + decksHtml;
}

/**
 * Rozpoczyna naukę dla decku
 */
function startStudy(deckId) {
    window.location.href = `/study?deckId=${deckId}`;
}

// escapeHtml() - używamy z shared.js
