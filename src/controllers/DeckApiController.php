<?php

require_once 'ApiController.php';
require_once __DIR__ . '/../repository/DeckRepository.php';
require_once __DIR__ . '/../repository/ClassRepository.php';

/**
 * Kontroler API dla operacji na deckach i kartach
 */
class DeckApiController extends ApiController
{
    private DeckRepository $deckRepository;
    private ClassRepository $classRepository;

    public function __construct()
    {
        parent::__construct();
        $this->deckRepository = DeckRepository::getInstance();
        $this->classRepository = ClassRepository::getInstance();
    }

    /**
     * GET /api/classes/{classId}/decks - lista decków
     * POST /api/classes/{classId}/decks - tworzenie decku (teacher only)
     */
    public function index(int $classId): void
    {
        $this->requireAuth();
        
        $class = $this->classRepository->getClassById($classId);
        
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        // Sprawdź dostęp
        if (!$this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole())) {
            $this->error('FORBIDDEN', 'Brak dostępu do tej klasy', 403);
        }
        
        if ($this->isPost()) {
            $this->createDeck($classId, $class);
            return;
        }
        
        $this->requireMethod('GET');
        
        $decks = $this->deckRepository->getDecksByClassId($classId);
        
        $this->success($decks);
    }

    /**
     * Tworzenie nowego decku
     */
    private function createDeck(int $classId, $class): void
    {
        // Tylko nauczyciel klasy może tworzyć decki
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do tworzenia zestawów', 403);
        }
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '') ?: null;
        $level = $input['level'] ?? 'beginner';
        $imageUrl = isset($input['imageUrl']) ? trim($input['imageUrl']) : null;
        $isPublic = isset($input['isPublic']) ? (bool) $input['isPublic'] : false;
        
        if (empty($title)) {
            $this->error('MISSING_TITLE', 'Tytuł zestawu jest wymagany', 400);
        }
        
        if (!in_array($level, ['beginner', 'intermediate', 'advanced'])) {
            $level = 'beginner';
        }
        
        // Walidacja URL obrazka
        if ($imageUrl && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        }
        
        try {
            $deckId = $this->deckRepository->createDeck($classId, $title, $description, $level, $imageUrl, $isPublic);
            $deck = $this->deckRepository->getDeckById($deckId);
            
            $this->success($deck->toArray(), 201);
        } catch (Exception $e) {
            error_log("Error creating deck: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas tworzenia zestawu', 500);
        }
    }

    /**
     * GET /api/decks/{deckId} - szczegóły decku
     * PUT /api/decks/{deckId} - aktualizacja decku
     * DELETE /api/decks/{deckId} - usunięcie decku
     */
    public function show(int $deckId): void
    {
        $this->requireAuth();
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        // Sprawdź dostęp do klasy
        $classId = $deck->getClassId();
        if (!$this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole())) {
            $this->error('FORBIDDEN', 'Brak dostępu do tego zestawu', 403);
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'DELETE') {
            $this->deleteDeck($deck);
            return;
        }
        
        if ($method === 'PUT') {
            $this->updateDeck($deck);
            return;
        }
        
        $this->requireMethod('GET');
        $this->success($deck->toArray());
    }

    /**
     * Aktualizacja decku
     */
    private function updateDeck($deck): void
    {
        // Tylko nauczyciel klasy może aktualizować decki
        $class = $this->classRepository->getClassById($deck->getClassId());
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do edycji zestawu', 403);
        }
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $title = trim($input['title'] ?? $deck->getTitle());
        $description = isset($input['description']) ? trim($input['description']) : $deck->getDescription();
        $level = $input['level'] ?? $deck->getLevel();
        $imageUrl = isset($input['imageUrl']) ? trim($input['imageUrl']) : $deck->getImageUrl();
        $isPublic = isset($input['isPublic']) ? (bool) $input['isPublic'] : null;
        
        if (empty($title)) {
            $this->error('MISSING_TITLE', 'Tytuł zestawu jest wymagany', 400);
        }
        
        if (!in_array($level, ['beginner', 'intermediate', 'advanced'])) {
            $level = 'beginner';
        }
        
        // Walidacja URL obrazka
        if ($imageUrl && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        }
        
        try {
            $this->deckRepository->updateDeck($deck->getId(), $title, $description, $level, $imageUrl, $isPublic);
            $updatedDeck = $this->deckRepository->getDeckById($deck->getId());
            
            $this->success($updatedDeck->toArray());
        } catch (Exception $e) {
            error_log("Error updating deck: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas aktualizacji zestawu', 500);
        }
    }

    /**
     * Usunięcie decku
     */
    private function deleteDeck($deck): void
    {
        // Tylko nauczyciel klasy może usuwać decki
        $class = $this->classRepository->getClassById($deck->getClassId());
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do usunięcia zestawu', 403);
        }
        
        try {
            $this->deckRepository->deleteDeck($deck->getId());
            $this->success(['message' => 'Zestaw został usunięty']);
        } catch (Exception $e) {
            error_log("Error deleting deck: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas usuwania zestawu', 500);
        }
    }

    /**
     * GET /api/decks/{deckId}/cards - lista kart
     * POST /api/decks/{deckId}/cards - tworzenie karty (teacher only)
     */
    public function cards(int $deckId): void
    {
        $this->requireAuth();
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        // Sprawdź dostęp do klasy
        $classId = $deck->getClassId();
        if (!$this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole())) {
            $this->error('FORBIDDEN', 'Brak dostępu do tego zestawu', 403);
        }
        
        if ($this->isPost()) {
            $this->createCard($deck);
            return;
        }
        
        $this->requireMethod('GET');
        
        $cards = $this->deckRepository->getCardsByDeckId($deckId);
        
        $this->success($cards);
    }

    /**
     * Tworzenie nowej karty
     */
    private function createCard($deck): void
    {
        // Tylko nauczyciel klasy może tworzyć karty
        $class = $this->classRepository->getClassById($deck->getClassId());
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do tworzenia fiszek', 403);
        }
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $front = trim($input['front'] ?? '');
        $back = trim($input['back'] ?? '');
        $imagePath = isset($input['imagePath']) ? trim($input['imagePath']) : null;
        
        if (empty($front) || empty($back)) {
            $this->error('MISSING_CONTENT', 'Przód i tył fiszki są wymagane', 400);
        }
        
        try {
            $cardId = $this->deckRepository->createCard($deck->getId(), $front, $back, $imagePath);
            $card = $this->deckRepository->getCardById($cardId);
            
            $this->success($card->toArray(), 201);
        } catch (Exception $e) {
            error_log("Error creating card: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas tworzenia fiszki', 500);
        }
    }
}
