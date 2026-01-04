<?php

require_once 'Repository.php';
require_once __DIR__ . '/../model/Progress.php';

class ProgressRepository extends Repository
{
    private static ?ProgressRepository $instance = null;

    public static function getInstance(): ProgressRepository
    {
        if (self::$instance === null) {
            self::$instance = new ProgressRepository();
        }
        return self::$instance;
    }

    /**
     * Pobiera lub tworzy progres dla karty
     */
    public function getOrCreateProgress(int $userId, int $cardId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM progress WHERE user_id = :user_id AND card_id = :card_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $cardId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return $row;
        }
        
        // Utwórz nowy progres
        $stmt = $this->database->connect()->prepare('
            INSERT INTO progress (user_id, card_id, status, correct_streak, wrong_streak)
            VALUES (:user_id, :card_id, \'new\', 0, 0)
            RETURNING *
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $cardId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Zapisuje odpowiedź użytkownika
     */
    public function recordAnswer(int $userId, int $cardId, bool $correct): bool
    {
        $progress = $this->getOrCreateProgress($userId, $cardId);
        
        if ($correct) {
            $newCorrectStreak = (int) $progress['correct_streak'] + 1;
            $newWrongStreak = 0;
            // Po 3 poprawnych odpowiedziach z rzędu - oznacz jako "known"
            $newStatus = $newCorrectStreak >= 3 ? 'known' : 'learning';
        } else {
            $newCorrectStreak = 0;
            $newWrongStreak = (int) $progress['wrong_streak'] + 1;
            $newStatus = 'learning';
        }
        
        $stmt = $this->database->connect()->prepare('
            UPDATE progress 
            SET status = :status, 
                correct_streak = :correct_streak, 
                wrong_streak = :wrong_streak,
                last_reviewed = NOW()
            WHERE user_id = :user_id AND card_id = :card_id
        ');
        
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':correct_streak', $newCorrectStreak, PDO::PARAM_INT);
        $stmt->bindParam(':wrong_streak', $newWrongStreak, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $cardId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Pobiera następną kartę do nauki (priorytet: new > learning > known)
     * Jeśli wszystkie karty są 'known', zwraca null (zakończono naukę)
     */
    public function getNextCardForStudy(int $userId, int $deckId): ?array
    {
        // Najpierw karty bez progresu lub ze statusem 'new' lub 'learning'
        $stmt = $this->database->connect()->prepare('
            SELECT c.*, COALESCE(p.status, \'new\') as progress_status
            FROM cards c
            LEFT JOIN progress p ON c.id = p.card_id AND p.user_id = :user_id
            WHERE c.deck_id = :deck_id
            AND (p.status IS NULL OR p.status IN (\'new\', \'learning\'))
            ORDER BY 
                CASE COALESCE(p.status, \'new\')
                    WHEN \'new\' THEN 1
                    WHEN \'learning\' THEN 2
                    ELSE 3
                END,
                p.last_reviewed ASC NULLS FIRST
            LIMIT 1
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Jeśli wszystkie karty są 'known', zwracamy null (ukończono zestaw)
        return $row ?: null;
    }

    /**
     * Pobiera statystyki progresu dla decku
     */
    public function getDeckProgress(int $userId, int $deckId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                COUNT(c.id) as total_cards,
                COUNT(CASE WHEN p.status = \'known\' THEN 1 END) as known_cards,
                COUNT(CASE WHEN p.status = \'learning\' THEN 1 END) as learning_cards,
                COUNT(CASE WHEN p.status = \'new\' OR p.status IS NULL THEN 1 END) as new_cards
            FROM cards c
            LEFT JOIN progress p ON c.id = p.card_id AND p.user_id = :user_id
            WHERE c.deck_id = :deck_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Pobiera ogólne statystyki użytkownika
     */
    public function getUserStats(int $userId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                COUNT(DISTINCT p.card_id) as total_studied,
                COUNT(CASE WHEN p.status = \'known\' THEN 1 END) as total_known,
                COUNT(CASE WHEN p.status = \'learning\' THEN 1 END) as total_learning
            FROM progress p
            WHERE p.user_id = :user_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Pobiera szczegółowy progres użytkownika po wszystkich deckach
     */
    public function getUserProgressByDecks(int $userId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 
                d.id as deck_id,
                d.title as deck_title,
                cl.name as class_name,
                COUNT(c.id) as total_cards,
                COUNT(CASE WHEN p.status = \'known\' THEN 1 END) as known_cards,
                COUNT(CASE WHEN p.status = \'learning\' THEN 1 END) as learning_cards
            FROM decks d
            JOIN classes cl ON d.class_id = cl.id
            JOIN class_members cm ON cl.id = cm.class_id AND cm.student_id = :user_id
            LEFT JOIN cards c ON d.id = c.deck_id
            LEFT JOIN progress p ON c.id = p.card_id AND p.user_id = :user_id
            GROUP BY d.id, d.title, cl.name
            ORDER BY cl.name, d.title
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
