-- Migracja: Dodanie tabeli password_resets
-- Uruchom ten skrypt jeśli baza danych już istnieje

-- Tabela tokenów resetowania hasła
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dodaj indeks dla szybszego wyszukiwania po tokenie
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);

-- Dodaj indeks dla szybszego czyszczenia wygasłych tokenów
CREATE INDEX IF NOT EXISTS idx_password_resets_expires ON password_resets(expires_at);
