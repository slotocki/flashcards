-- Migration: Oddzielenie zestawów od klas
-- Zestawy należą teraz do nauczyciela, nie do klasy
-- Można je przypisywać do wielu klas przez tabelę pośrednią

-- 1. Dodaj teacher_id do decks
ALTER TABLE decks ADD COLUMN teacher_id INTEGER REFERENCES users(id) ON DELETE CASCADE;

-- 2. Uzupełnij teacher_id na podstawie class_id
UPDATE decks d SET teacher_id = (
    SELECT c.teacher_id FROM classes c WHERE c.id = d.class_id
);

-- 3. Utwórz tabelę pośrednią class_decks
CREATE TABLE class_decks (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    deck_id INTEGER NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(class_id, deck_id)
);

-- 4. Przenieś istniejące przypisania do nowej tabeli
INSERT INTO class_decks (class_id, deck_id)
SELECT class_id, id FROM decks WHERE class_id IS NOT NULL;

-- 5. Usuń obowiązkowość class_id w decks
ALTER TABLE decks ALTER COLUMN class_id DROP NOT NULL;

-- 6. Ustaw teacher_id jako obowiązkowe
ALTER TABLE decks ALTER COLUMN teacher_id SET NOT NULL;

-- Po tej migracji decks.class_id pozostaje dla kompatybilności wstecznej
-- ale właściwą relacją jest class_decks
