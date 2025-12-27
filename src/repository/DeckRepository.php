<?php

require_once 'Repository.php';
require_once __DIR__ . '/../model/Deck.php';
require_once __DIR__ . '/../model/Card.php';

class DeckRepository extends Repository
{
    private static ?DeckRepository $instance = null;

    public static function getInstance(): DeckRepository
    {
        if (self::$instance === null) {
            self::$instance = new DeckRepository();
        }
        return self::$instance;
    }

    /**
     * Tworzy nowy deck
     */
    public function createDeck(int $classId, string $title, ?string $description = null, string $level = 'beginner', ?string $imageUrl = null, bool $isPublic = false): int
    {
        $shareToken = $isPublic ? $this->generateShareToken() : null;
        
        $stmt = $this->database->connect()->prepare('
            INSERT INTO decks (class_id, title, description, level, image_url, is_public, share_token)
            VALUES (:class_id, :title, :description, :level, :image_url, :is_public, :share_token)
            RETURNING id
        ');
        
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':level', $level, PDO::PARAM_STR);
        $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);
        $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_BOOL);
        $stmt->bindParam(':share_token', $shareToken, PDO::PARAM_STR);
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['id'];
    }

    /**
     * Generuje unikalny token do udostępniania
     */
    private function generateShareToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Pobiera deck po ID z pełnymi danymi community
     */
    public function getDeckById(int $id): ?Deck
    {
        $stmt = $this->database->connect()->prepare("
            SELECT d.*, 
                   COUNT(DISTINCT c.id) as card_count,
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(DISTINCT r.id) as ratings_count,
                   cl.name as class_name,
                   CONCAT(u.firstname, ' ', u.lastname) as teacher_name
            FROM decks d
            LEFT JOIN cards c ON d.id = c.deck_id
            LEFT JOIN deck_ratings r ON d.id = r.deck_id
            LEFT JOIN classes cl ON d.class_id = cl.id
            LEFT JOIN users u ON cl.teacher_id = u.id
            WHERE d.id = :id
            GROUP BY d.id, cl.name, u.firstname, u.lastname
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToDeck($row);
    }

    /**
     * Pobiera deck po tokenie udostępniania
     */
    public function getDeckByShareToken(string $token): ?Deck
    {
        $stmt = $this->database->connect()->prepare("
            SELECT d.*, 
                   COUNT(DISTINCT c.id) as card_count,
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(DISTINCT r.id) as ratings_count,
                   cl.name as class_name,
                   CONCAT(u.firstname, ' ', u.lastname) as teacher_name
            FROM decks d
            LEFT JOIN cards c ON d.id = c.deck_id
            LEFT JOIN deck_ratings r ON d.id = r.deck_id
            LEFT JOIN classes cl ON d.class_id = cl.id
            LEFT JOIN users u ON cl.teacher_id = u.id
            WHERE d.share_token = :token AND d.is_public = true
            GROUP BY d.id, cl.name, u.firstname, u.lastname
        ");
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        // Zwiększ licznik wyświetleń
        $this->incrementViewsCount($row['id']);
        
        return $this->mapRowToDeck($row);
    }

    /**
     * Zwiększa licznik wyświetleń
     */
    public function incrementViewsCount(int $deckId): void
    {
        $stmt = $this->database->connect()->prepare('
            UPDATE decks SET views_count = views_count + 1 WHERE id = :id
        ');
        $stmt->bindParam(':id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Pobiera decki dla klasy
     */
    public function getDecksByClassId(int $classId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT d.*, 
                   COUNT(DISTINCT c.id) as card_count,
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(DISTINCT r.id) as ratings_count
            FROM decks d
            LEFT JOIN cards c ON d.id = c.deck_id
            LEFT JOIN deck_ratings r ON d.id = r.deck_id
            WHERE d.class_id = :class_id
            GROUP BY d.id
            ORDER BY d.created_at DESC
        ');
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        $decks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $decks[] = $this->mapRowToDeck($row)->toArray();
        }
        return $decks;
    }

    /**
     * Pobiera publiczne decki (community)
     */
    public function getPublicDecks(string $search = '', string $sortBy = 'popular', int $limit = 20, int $offset = 0): array
    {
        $orderBy = match($sortBy) {
            'newest' => 'd.created_at DESC',
            'rating' => 'average_rating DESC, ratings_count DESC',
            'views' => 'd.views_count DESC',
            default => 'd.views_count DESC, average_rating DESC' // popular
        };
        
        $searchCondition = '';
        if (!empty($search)) {
            $searchCondition = 'AND (LOWER(d.title) LIKE LOWER(:search) OR LOWER(d.description) LIKE LOWER(:search) OR LOWER(cl.name) LIKE LOWER(:search))';
        }
        
        $stmt = $this->database->connect()->prepare("
            SELECT d.*, 
                   COUNT(DISTINCT c.id) as card_count,
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(DISTINCT r.id) as ratings_count,
                   cl.name as class_name,
                   cl.language as class_language,
                   CONCAT(u.firstname, ' ', u.lastname) as teacher_name
            FROM decks d
            LEFT JOIN cards c ON d.id = c.deck_id
            LEFT JOIN deck_ratings r ON d.id = r.deck_id
            LEFT JOIN classes cl ON d.class_id = cl.id
            LEFT JOIN users u ON cl.teacher_id = u.id
            WHERE d.is_public = true {$searchCondition}
            GROUP BY d.id, cl.name, cl.language, u.firstname, u.lastname
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ");
        
        if (!empty($search)) {
            $searchParam = '%' . $search . '%';
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $decks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $deck = $this->mapRowToDeck($row);
            $deckArray = $deck->toArray();
            $deckArray['language'] = $row['class_language'] ?? null;
            $decks[] = $deckArray;
        }
        return $decks;
    }

    /**
     * Zlicza publiczne decki
     */
    public function countPublicDecks(string $search = ''): int
    {
        $searchCondition = '';
        if (!empty($search)) {
            $searchCondition = 'AND (LOWER(d.title) LIKE LOWER(:search) OR LOWER(d.description) LIKE LOWER(:search) OR LOWER(cl.name) LIKE LOWER(:search))';
        }
        
        $stmt = $this->database->connect()->prepare("
            SELECT COUNT(DISTINCT d.id) as total
            FROM decks d
            LEFT JOIN classes cl ON d.class_id = cl.id
            WHERE d.is_public = true {$searchCondition}
        ");
        
        if (!empty($search)) {
            $searchParam = '%' . $search . '%';
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Pobiera subskrybowane decki użytkownika
     */
    public function getSubscribedDecks(int $userId): array
    {
        $stmt = $this->database->connect()->prepare("
            SELECT d.*, 
                   COUNT(DISTINCT c.id) as card_count,
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(DISTINCT r.id) as ratings_count,
                   cl.name as class_name,
                   cl.language as class_language,
                   CONCAT(u.firstname, ' ', u.lastname) as teacher_name,
                   cs.subscribed_at
            FROM community_subscriptions cs
            JOIN decks d ON cs.deck_id = d.id
            LEFT JOIN cards c ON d.id = c.deck_id
            LEFT JOIN deck_ratings r ON d.id = r.deck_id
            LEFT JOIN classes cl ON d.class_id = cl.id
            LEFT JOIN users u ON cl.teacher_id = u.id
            WHERE cs.user_id = :user_id AND d.is_public = true
            GROUP BY d.id, cl.name, cl.language, u.firstname, u.lastname, cs.subscribed_at
            ORDER BY cs.subscribed_at DESC
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $decks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $deck = $this->mapRowToDeck($row);
            $deckArray = $deck->toArray();
            $deckArray['language'] = $row['class_language'] ?? null;
            $deckArray['subscribedAt'] = $row['subscribed_at'];
            $decks[] = $deckArray;
        }
        return $decks;
    }

    /**
     * Subskrybuje deck
     */
    public function subscribeToDeck(int $userId, int $deckId): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                INSERT INTO community_subscriptions (user_id, deck_id)
                VALUES (:user_id, :deck_id)
                ON CONFLICT (user_id, deck_id) DO NOTHING
            ');
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Anuluje subskrypcję decku
     */
    public function unsubscribeFromDeck(int $userId, int $deckId): bool
    {
        $stmt = $this->database->connect()->prepare('
            DELETE FROM community_subscriptions 
            WHERE user_id = :user_id AND deck_id = :deck_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Sprawdza czy użytkownik subskrybuje deck
     */
    public function isSubscribed(int $userId, int $deckId): bool
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 1 FROM community_subscriptions 
            WHERE user_id = :user_id AND deck_id = :deck_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }

    /**
     * Ocenia deck
     */
    public function rateDeck(int $userId, int $deckId, int $rating): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }
        
        try {
            $stmt = $this->database->connect()->prepare('
                INSERT INTO deck_ratings (user_id, deck_id, rating)
                VALUES (:user_id, :deck_id, :rating)
                ON CONFLICT (user_id, deck_id) 
                DO UPDATE SET rating = :rating, created_at = NOW()
            ');
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
            $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Pobiera ocenę użytkownika dla decku
     */
    public function getUserRating(int $userId, int $deckId): ?int
    {
        $stmt = $this->database->connect()->prepare('
            SELECT rating FROM deck_ratings 
            WHERE user_id = :user_id AND deck_id = :deck_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['rating'] : null;
    }

    /**
     * Aktualizuje deck (z nowymi polami)
     */
    public function updateDeck(int $id, string $title, ?string $description, string $level, ?string $imageUrl = null, ?bool $isPublic = null): bool
    {
        $deck = $this->getDeckById($id);
        if (!$deck) {
            return false;
        }
        
        // Jeśli deck staje się publiczny, wygeneruj token
        $shareToken = $deck->getShareToken();
        if ($isPublic === true && empty($shareToken)) {
            $shareToken = $this->generateShareToken();
        } elseif ($isPublic === false) {
            $shareToken = null;
        }
        
        $stmt = $this->database->connect()->prepare('
            UPDATE decks 
            SET title = :title, 
                description = :description, 
                level = :level,
                image_url = :image_url,
                is_public = COALESCE(:is_public, is_public),
                share_token = :share_token
            WHERE id = :id
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':level', $level, PDO::PARAM_STR);
        $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);
        $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_BOOL);
        $stmt->bindParam(':share_token', $shareToken, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Usuwa deck
     */
    public function deleteDeck(int $id): bool
    {
        $stmt = $this->database->connect()->prepare('DELETE FROM decks WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Tworzy nową kartę (fiszkę)
     */
    public function createCard(int $deckId, string $front, string $back, ?string $imagePath = null): int
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO cards (deck_id, front, back, image_path)
            VALUES (:deck_id, :front, :back, :image_path)
            RETURNING id
        ');
        
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->bindParam(':front', $front, PDO::PARAM_STR);
        $stmt->bindParam(':back', $back, PDO::PARAM_STR);
        $stmt->bindParam(':image_path', $imagePath, PDO::PARAM_STR);
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['id'];
    }

    /**
     * Pobiera kartę po ID
     */
    public function getCardById(int $id): ?Card
    {
        $stmt = $this->database->connect()->prepare('SELECT * FROM cards WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToCard($row);
    }

    /**
     * Pobiera karty dla decku
     */
    public function getCardsByDeckId(int $deckId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM cards WHERE deck_id = :deck_id ORDER BY id
        ');
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
        
        $cards = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cards[] = $this->mapRowToCard($row)->toArray();
        }
        return $cards;
    }

    /**
     * Aktualizuje kartę
     */
    public function updateCard(int $id, string $front, string $back, ?string $imagePath): bool
    {
        $stmt = $this->database->connect()->prepare('
            UPDATE cards SET front = :front, back = :back, image_path = :image_path
            WHERE id = :id
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':front', $front, PDO::PARAM_STR);
        $stmt->bindParam(':back', $back, PDO::PARAM_STR);
        $stmt->bindParam(':image_path', $imagePath, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Usuwa kartę
     */
    public function deleteCard(int $id): bool
    {
        $stmt = $this->database->connect()->prepare('DELETE FROM cards WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Pobiera classId dla decku
     */
    public function getClassIdForDeck(int $deckId): ?int
    {
        $stmt = $this->database->connect()->prepare('SELECT class_id FROM decks WHERE id = :id');
        $stmt->bindParam(':id', $deckId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['class_id'] : null;
    }

    private function mapRowToDeck(array $row): Deck
    {
        return new Deck(
            (int) $row['id'],
            (int) $row['class_id'],
            $row['title'],
            $row['description'] ?? null,
            $row['level'] ?? 'beginner',
            $row['image_url'] ?? null,
            isset($row['is_public']) ? (bool) $row['is_public'] : false,
            $row['share_token'] ?? null,
            isset($row['views_count']) ? (int) $row['views_count'] : 0,
            $row['created_at'] ?? null,
            isset($row['card_count']) ? (int) $row['card_count'] : null,
            isset($row['average_rating']) ? (float) $row['average_rating'] : null,
            isset($row['ratings_count']) ? (int) $row['ratings_count'] : null,
            $row['class_name'] ?? null,
            $row['teacher_name'] ?? null
        );
    }

    private function mapRowToCard(array $row): Card
    {
        return new Card(
            (int) $row['id'],
            (int) $row['deck_id'],
            $row['front'],
            $row['back'],
            $row['image_path'] ?? null,
            $row['created_at'] ?? null
        );
    }
}
