/**
 * API Helper - moduł do komunikacji z backendem
 */
const API = {
    baseUrl: '',
    
    /**
     * Wykonuje zapytanie do API
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        };
        
        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            // Jeśli odpowiedź nie jest ok i nie ma danych JSON
            if (!response.ok && !data) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    },
    
    /**
     * GET request
     */
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },
    
    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },

    // ==================== AUTH ====================
    
    auth: {
        async login(email, password) {
            return API.post('/api/auth/login', { email, password });
        },
        
        async register(data) {
            return API.post('/api/auth/register', data);
        },
        
        async logout() {
            return API.post('/api/auth/logout');
        },
        
        async me() {
            return API.get('/api/auth/me');
        }
    },
    
    // ==================== CLASSES ====================
    
    classes: {
        async list() {
            return API.get('/api/classes');
        },
        
        async get(id) {
            return API.get(`/api/classes/${id}`);
        },
        
        async create(data) {
            return API.post('/api/classes', data);
        },
        
        async join(joinCode) {
            return API.post('/api/classes/join', { joinCode });
        },
        
        async members(id) {
            return API.get(`/api/classes/${id}/members`);
        },
        
        async tasks(id) {
            return API.get(`/api/classes/${id}/tasks`);
        },
        
        async createTask(classId, data) {
            return API.post(`/api/classes/${classId}/tasks`, data);
        },
        
        async deleteTask(classId, taskId) {
            return API.delete(`/api/classes/${classId}/tasks/${taskId}`);
        }
    },
    
    // ==================== DECKS ====================
    
    decks: {
        async listByClass(classId) {
            return API.get(`/api/classes/${classId}/decks`);
        },
        
        async listTeacherDecks() {
            return API.get('/api/teacher/decks');
        },
        
        async get(id) {
            return API.get(`/api/decks/${id}`);
        },
        
        async create(data) {
            return API.post('/api/teacher/decks', data);
        },
        
        async update(id, data) {
            return API.put(`/api/decks/${id}`, data);
        },
        
        async delete(id) {
            return API.delete(`/api/decks/${id}`);
        },
        
        async assignToClasses(deckId, classIds) {
            return API.put(`/api/decks/${deckId}/assign-classes`, { classIds });
        },
        
        async cards(deckId) {
            return API.get(`/api/decks/${deckId}/cards`);
        },
        
        async createCard(deckId, data) {
            return API.post(`/api/decks/${deckId}/cards`, data);
        }
    },
    
    // ==================== STUDY ====================
    
    study: {
        async nextCard(deckId) {
            return API.get(`/api/study/next?deckId=${deckId}`);
        },
        
        async answer(cardId, answer) {
            return API.post('/api/progress/answer', { cardId, answer });
        }
    },
    
    // ==================== PROGRESS ====================
    
    progress: {
        async stats() {
            return API.get('/api/progress/stats');
        },
        
        async deck(deckId) {
            return API.get(`/api/progress/deck/${deckId}`);
        },
        
        async reset(deckId) {
            return API.post(`/api/progress/reset/${deckId}`);
        }
    }
};

// Export dla użycia w innych modułach
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}
