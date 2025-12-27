/**
 * Study module - tryb nauki fiszek (Wiem/Nie wiem)
 */
let currentCard = null;
let currentDeckId = null;
let isFlipped = false;

document.addEventListener('DOMContentLoaded', () => {
    initStudy();
});

/**
 * Inicjalizacja trybu nauki
 */
async function initStudy() {
    // Pobierz deckId z URL (obsługa obu formatów)
    const params = new URLSearchParams(window.location.search);
    currentDeckId = params.get('deckId') || params.get('deck');
    
    if (!currentDeckId) {
        showMessage('Nie wybrano zestawu do nauki', 'error');
        return;
    }
    
    // Elementy UI
    const flashcard = document.querySelector('.flashcard');
    const knowBtn = document.getElementById('knowBtn');
    const dontKnowBtn = document.getElementById('dontKnowBtn');
    const flipBtn = document.getElementById('flipBtn');
    
    // Obsługa odwracania fiszki
    if (flashcard) {
        flashcard.addEventListener('click', toggleFlip);
    }
    
    if (flipBtn) {
        flipBtn.addEventListener('click', toggleFlip);
    }
    
    // Obsługa przycisków odpowiedzi
    if (knowBtn) {
        knowBtn.addEventListener('click', () => answerCard('know'));
    }
    
    if (dontKnowBtn) {
        dontKnowBtn.addEventListener('click', () => answerCard('dont_know'));
    }
    
    // Obsługa klawiszy
    document.addEventListener('keydown', handleKeyPress);
    
    // Załaduj pierwszą kartę
    await loadNextCard();
    
    // Załaduj progres
    await loadDeckProgress();
}

/**
 * Ładuje następną kartę do nauki
 */
async function loadNextCard() {
    if (!currentDeckId) return;
    
    showLoading(true);
    
    try {
        const result = await API.study.nextCard(currentDeckId);
        
        if (result.ok) {
            if (result.data.card) {
                currentCard = result.data.card;
                displayCard(result.data);
                isFlipped = false;
                updateFlipState();
            } else {
                showCompleted();
            }
        } else {
            showMessage('Błąd: ' + (result.error?.message || 'Nie udało się załadować karty'), 'error');
        }
    } catch (error) {
        console.error('Error loading next card:', error);
        showMessage('Błąd ładowania karty', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Wyświetla kartę
 */
function displayCard(data) {
    const frontEl = document.querySelector('.card-front .card-content, .card-front');
    const backEl = document.querySelector('.card-back .card-content, .card-back');
    const titleEl = document.querySelector('.deck-title');
    const progressEl = document.querySelector('.card-progress');
    
    if (frontEl) {
        frontEl.innerHTML = `<p class="card-text">${escapeHtml(data.card.front)}</p>`;
        if (data.card.imagePath) {
            frontEl.innerHTML += `<img src="${data.card.imagePath}" alt="Card image" class="card-image">`;
        }
    }
    
    if (backEl) {
        backEl.innerHTML = `<p class="card-text">${escapeHtml(data.card.back)}</p>`;
    }
    
    if (titleEl) {
        titleEl.textContent = data.deckTitle || '';
    }
    
    if (progressEl && data.progress) {
        const status = data.progress.status;
        const statusLabels = {
            'new': 'Nowa',
            'learning': 'W nauce',
            'known': 'Opanowana'
        };
        progressEl.textContent = statusLabels[status] || status;
        progressEl.className = `card-progress status-${status}`;
    }
    
    // Pokaż przyciski odpowiedzi
    const controls = document.querySelector('.study-controls, .answer-buttons');
    if (controls) {
        controls.style.display = 'flex';
    }
}

/**
 * Przełącza widok fiszki (przód/tył)
 */
function toggleFlip() {
    isFlipped = !isFlipped;
    updateFlipState();
}

/**
 * Aktualizuje stan odwrócenia fiszki
 */
function updateFlipState() {
    const flashcard = document.querySelector('.flashcard');
    if (flashcard) {
        if (isFlipped) {
            flashcard.classList.add('flipped');
        } else {
            flashcard.classList.remove('flipped');
        }
    }
}

/**
 * Zapisuje odpowiedź i ładuje następną kartę
 */
async function answerCard(answer) {
    if (!currentCard) return;
    
    const buttons = document.querySelectorAll('.study-controls button, .answer-buttons button');
    buttons.forEach(btn => btn.disabled = true);
    
    try {
        const result = await API.study.answer(currentCard.id, answer);
        
        if (result.ok) {
            // Pokaż feedback
            showFeedback(answer === 'know');
            
            // Załaduj następną kartę po krótkim opóźnieniu
            setTimeout(async () => {
                await loadNextCard();
                await loadDeckProgress();
            }, 500);
        } else {
            showMessage('Błąd: ' + (result.error?.message || 'Nie udało się zapisać odpowiedzi'), 'error');
        }
    } catch (error) {
        console.error('Error answering card:', error);
        showMessage('Błąd zapisywania odpowiedzi', 'error');
    } finally {
        buttons.forEach(btn => btn.disabled = false);
    }
}

/**
 * Pokazuje feedback po odpowiedzi
 */
function showFeedback(isCorrect) {
    const flashcard = document.querySelector('.flashcard');
    if (flashcard) {
        flashcard.classList.add(isCorrect ? 'correct' : 'incorrect');
        setTimeout(() => {
            flashcard.classList.remove('correct', 'incorrect');
        }, 500);
    }
}

/**
 * Ładuje progres decku
 */
async function loadDeckProgress() {
    if (!currentDeckId) return;
    
    try {
        const result = await API.progress.deck(currentDeckId);
        
        if (result.ok) {
            displayDeckProgress(result.data);
        }
    } catch (error) {
        console.error('Error loading deck progress:', error);
    }
}

/**
 * Wyświetla progres decku
 */
function displayDeckProgress(data) {
    const progressBar = document.querySelector('.progress-bar-fill');
    const progressText = document.querySelector('.progress-text');
    const statsEl = document.querySelector('.deck-stats');
    
    if (progressBar) {
        progressBar.style.width = `${data.progress}%`;
    }
    
    if (progressText) {
        progressText.textContent = `${data.knownCards}/${data.totalCards} opanowanych (${data.progress}%)`;
    }
    
    if (statsEl) {
        statsEl.innerHTML = `
            <span class="stat stat-new">Nowe: ${data.newCards}</span>
            <span class="stat stat-learning">W nauce: ${data.learningCards}</span>
            <span class="stat stat-known">Opanowane: ${data.knownCards}</span>
        `;
    }
}

/**
 * Pokazuje komunikat o ukończeniu wszystkich kart
 */
function showCompleted() {
    const cardContainer = document.querySelector('.flashcard-container, .study-container');
    if (cardContainer) {
        cardContainer.innerHTML = `
            <div class="completed-message">
                <h2>🎉 Gratulacje!</h2>
                <p>Przejrzałeś wszystkie karty w tym zestawie!</p>
                <button class="btn-primary" onclick="window.location.reload()">Powtórz zestaw</button>
                <button class="btn-secondary" onclick="window.history.back()">Wróć do klasy</button>
            </div>
        `;
    }
}

/**
 * Obsługa klawiszy
 */
function handleKeyPress(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    switch(e.key) {
        case ' ':
        case 'Enter':
            e.preventDefault();
            toggleFlip();
            break;
        case 'ArrowRight':
        case '1':
            e.preventDefault();
            if (currentCard) answerCard('know');
            break;
        case 'ArrowLeft':
        case '2':
            e.preventDefault();
            if (currentCard) answerCard('dont_know');
            break;
    }
}

/**
 * Pokazuje/ukrywa loading
 */
function showLoading(show) {
    const loader = document.querySelector('.loader');
    if (loader) {
        loader.style.display = show ? 'block' : 'none';
    }
}

/**
 * Pokazuje komunikat
 */
function showMessage(message, type = 'info') {
    const container = document.querySelector('.study-container, main');
    if (container) {
        const msgEl = document.createElement('div');
        msgEl.className = `message message-${type}`;
        msgEl.textContent = message;
        container.insertBefore(msgEl, container.firstChild);
        
        setTimeout(() => msgEl.remove(), 5000);
    }
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
