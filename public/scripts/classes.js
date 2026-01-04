/**
 * Classes module - obsługa klas (lista, dołączanie, tworzenie)
 */
document.addEventListener('DOMContentLoaded', () => {
    initClasses();
});

/**
 * Inicjalizacja modułu klas
 */
async function initClasses() {
    const classList = document.querySelector('.class-list');
    const addClassLink = document.querySelector('.add-class');
    const joinClassModal = document.getElementById('joinClassModal');
    const createClassModal = document.getElementById('createClassModal');
    
    // Załaduj listę klas
    if (classList) {
        await loadClasses();
    }
    
    // Obsługa linku "dołącz do nowej klasie"
    if (addClassLink) {
        addClassLink.addEventListener('click', (e) => {
            e.preventDefault();
            showJoinClassModal();
        });
    }
    
    // Obsługa modalu dołączania do klasy
    if (joinClassModal) {
        setupJoinClassModal();
    }
    
    // Obsługa modalu tworzenia klasy (dla nauczyciela)
    if (createClassModal) {
        setupCreateClassModal();
    }
}

/**
 * Ładuje listę klas
 */
async function loadClasses() {
    const classList = document.querySelector('.class-list');
    if (!classList) return;
    
    try {
        const result = await API.classes.list();
        
        if (result.ok) {
            renderClasses(result.data);
        } else {
            classList.innerHTML = '<p class="error">Nie udało się załadować klas</p>';
        }
    } catch (error) {
        console.error('Error loading classes:', error);
        classList.innerHTML = '<p class="error">Błąd ładowania klas</p>';
    }
}

/**
 * Renderuje listę klas
 */
function renderClasses(classes) {
    const classList = document.querySelector('.class-list');
    if (!classList) return;
    
    // Sprawdź czy jest template
    const template = document.getElementById('classItemTemplate');
    
    if (classes.length === 0) {
        classList.innerHTML = '<p class="no-classes">Nie należysz jeszcze do żadnej klasy</p>';
        return;
    }
    
    classList.innerHTML = '';
    
    classes.forEach(classItem => {
        const element = createClassElement(classItem, template);
        classList.appendChild(element);
    });
}

/**
 * Tworzy element klasy
 */
function createClassElement(classData, template) {
    let element;

    if (template) {
        element = template.content.cloneNode(true).firstElementChild;
    } else {
        element = document.createElement('div');
        element.className = 'class-item';
        element.innerHTML = `
            <div class="class-info">
                <img class="flag" src="${getLanguageFlagUrl(classData.language)}" alt="${classData.language || 'unknown'}">
                <div>
                    <h3 class="class-name"></h3>
                    <p class="class-language"></p>
                    <p class="teacher"></p>
                </div>
            </div>
            <button class="btn-primary">Wejdź</button>
        `;
    }

    // Wypełnij dane
    const nameEl = element.querySelector('.class-name, h3');
    const teacherEl = element.querySelector('.teacher');
    const langEl = element.querySelector('.class-language');
    const button = element.querySelector('button');

    if (nameEl) nameEl.textContent = classData.name;
    if (teacherEl) teacherEl.textContent = classData.teacherName || '';
    if (langEl) langEl.textContent = getLanguageName(classData.language);

    if (button) {
        button.addEventListener('click', () => {
            window.location.href = `/class?id=${classData.id}`;
        });
    }

    element.dataset.classId = classData.id;

    return element;
}

// getLanguageFlagUrl() i getLanguageName() - używamy z shared.js

/**
 * Pokazuje modal dołączania do klasy
 */
function showJoinClassModal() {
    const modal = document.getElementById('joinClassModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    } else {
        // Jeśli nie ma modalu, utwórz prosty prompt
        const code = prompt('Wpisz kod dołączenia do klasy:');
        if (code) {
            joinClass(code);
        }
    }
}

/**
 * Konfiguracja modalu dołączania do klasy
 */
function setupJoinClassModal() {
    const modal = document.getElementById('joinClassModal');
    const form = modal.querySelector('form') || modal.querySelector('.join-form');
    const closeBtn = modal.querySelector('.close-btn, .close');
    const cancelBtn = modal.querySelector('.cancel-btn');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
            modal.style.display = 'none';
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            modal.classList.remove('active');
            modal.style.display = 'none';
        });
    }
    
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const codeInput = form.querySelector('input[name="joinCode"]') || form.querySelector('input');
            const code = codeInput?.value?.trim();
            
            if (code) {
                await joinClass(code);
            }
        });
    }
    
    // Zamknij modal po kliknięciu poza nim
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
            modal.style.display = 'none';
        }
    });
}

/**
 * Dołącza do klasy za pomocą kodu
 */
async function joinClass(code) {
    try {
        const result = await API.classes.join(code);
        
        if (result.ok) {
            alert('Dołączono do klasy: ' + result.data.class.name);
            // Odśwież listę klas
            await loadClasses();
            
            // Zamknij modal jeśli jest otwarty
            const modal = document.getElementById('joinClassModal');
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        } else {
            alert('Błąd: ' + (result.error?.message || 'Nie udało się dołączyć do klasy'));
        }
    } catch (error) {
        console.error('Error joining class:', error);
        alert('Wystąpił błąd podczas dołączania do klasy');
    }
}

/**
 * Konfiguracja modalu tworzenia klasy (dla nauczyciela)
 */
function setupCreateClassModal() {
    const modal = document.getElementById('createClassModal');
    const form = modal.querySelector('form');
    const closeBtn = modal.querySelector('.close-btn, .close');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
            modal.style.display = 'none';
        });
    }
    
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const name = form.querySelector('input[name="name"]')?.value?.trim();
            const description = form.querySelector('textarea[name="description"]')?.value?.trim();
            const language = form.querySelector('select[name="language"]')?.value;
            
            if (!name) {
                alert('Nazwa klasy jest wymagana');
                return;
            }
            
            try {
                const result = await API.classes.create({ name, description, language });
                
                if (result.ok) {
                    alert(`Klasa utworzona!\nKod dołączenia: ${result.data.joinCode}`);
                    await loadClasses();
                    modal.classList.remove('active');
                    modal.style.display = 'none';
                } else {
                    alert('Błąd: ' + (result.error?.message || 'Nie udało się utworzyć klasy'));
                }
            } catch (error) {
                console.error('Error creating class:', error);
                alert('Wystąpił błąd podczas tworzenia klasy');
            }
        });
    }
}

/**
 * Pokazuje modal tworzenia klasy
 */
function showCreateClassModal() {
    const modal = document.getElementById('createClassModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}
