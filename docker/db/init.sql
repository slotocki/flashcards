-- =====================================================
-- MemoRise Database Schema
-- =====================================================

-- Tabela użytkowników
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'student' CHECK (role IN ('student', 'teacher', 'admin')),
    bio TEXT,
    enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela klas
CREATE TABLE classes (
    id SERIAL PRIMARY KEY,
    teacher_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    join_code VARCHAR(8) UNIQUE NOT NULL,
    language VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela członków klasy (relacja wiele-do-wielu między studentami a klasami)
CREATE TABLE class_members (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(class_id, student_id)
);

-- Tabela zestawów (decków)
CREATE TABLE decks (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    level VARCHAR(20) DEFAULT 'beginner' CHECK (level IN ('beginner', 'intermediate', 'advanced')),
    image_url VARCHAR(500),
    is_public BOOLEAN DEFAULT FALSE,
    share_token VARCHAR(32) UNIQUE,
    views_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela ocen zestawów
CREATE TABLE deck_ratings (
    id SERIAL PRIMARY KEY,
    deck_id INTEGER NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(deck_id, user_id)
);

-- Tabela subskrypcji społeczności (użytkownicy zapisują się do publicznych zestawów)
CREATE TABLE community_subscriptions (
    id SERIAL PRIMARY KEY,
    deck_id INTEGER NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(deck_id, user_id)
);

-- Tabela fiszek (kart)
CREATE TABLE cards (
    id SERIAL PRIMARY KEY,
    deck_id INTEGER NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    front TEXT NOT NULL,
    back TEXT NOT NULL,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela progresu nauki
CREATE TABLE progress (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    card_id INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'new' CHECK (status IN ('new', 'learning', 'known')),
    correct_streak INTEGER DEFAULT 0,
    wrong_streak INTEGER DEFAULT 0,
    last_reviewed TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, card_id)
);

-- Tabela zadań (assignments)
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    deck_id INTEGER REFERENCES decks(id) ON DELETE SET NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Dane testowe
-- =====================================================

-- Użytkownicy testowi (hasło: Password123)
INSERT INTO users (firstname, lastname, email, role, bio, enabled, password)
VALUES 
    ('Jan', 'Kowalski', 'jan.kowalski@example.com', 'student', 'Lubi programować w JS i PL/SQL.', TRUE, '$2y$12$uq2Zc3TUIRwAPnTZl1eMK.VIWIo2hY.etTPqxR5dffHecQjhm9vni'),
    ('Anna', 'Nowak', 'anna.nowak@example.com', 'teacher', 'Nauczycielka języka niemieckiego.', TRUE, '$2y$12$uq2Zc3TUIRwAPnTZl1eMK.VIWIo2hY.etTPqxR5dffHecQjhm9vni'),
    ('Admin', 'System', 'admin@memorise.pl', 'admin', 'Administrator systemu.', TRUE, '$2y$12$uq2Zc3TUIRwAPnTZl1eMK.VIWIo2hY.etTPqxR5dffHecQjhm9vni');

-- Klasa testowa
INSERT INTO classes (teacher_id, name, description, join_code, language)
VALUES 
    (2, 'Klasa 4a - niemiecki', 'Kurs języka niemieckiego dla klasy 4a', 'ABC12345', 'de');

-- Student dołącza do klasy
INSERT INTO class_members (class_id, student_id)
VALUES (1, 1);

-- Zestaw testowy
INSERT INTO decks (class_id, title, description, level)
VALUES 
    (1, 'Die Tiere', 'Słownictwo - zwierzęta', 'beginner');

-- Fiszki testowe
INSERT INTO cards (deck_id, front, back)
VALUES 
    (1, 'der Hund', 'pies'),
    (1, 'die Katze', 'kot'),
    (1, 'der Vogel', 'ptak'),
    (1, 'das Pferd', 'koń'),
    (1, 'die Kuh', 'krowa');

-- Zadanie testowe
INSERT INTO tasks (class_id, deck_id, title, description, due_date)
VALUES 
    (1, 1, 'Nauka słówek - die Tiere', 'Proszę nauczyć się słówek ze zwierzętami do poniedziałku', NOW() + INTERVAL '7 days');