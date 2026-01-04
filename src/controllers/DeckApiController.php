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
     * GET /api/classes/{classId}/decks - lista decków przypisanych do klasy
     * POST /api/classes/{classId}/decks - przypisanie istniejącego decku do klasy
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
            $this->assignDeckToClass($classId, $class);
            return;
        }
        
        $this->requireMethod('GET');
        
        $decks = $this->deckRepository->getDecksByClassId($classId);
        
        $this->success($decks);
    }
    
    /**
     * Przypisuje deck do klasy
     */
    private function assignDeckToClass(int $classId, $class): void
    {
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień', 403);
        }
        
        $input = $this->getJsonInput();
        $deckId = (int) ($input['deckId'] ?? 0);
        
        if (!$deckId) {
            $this->error('MISSING_DECK', 'Nie podano ID zestawu', 400);
        }
        
        // Sprawdź czy deck istnieje i należy do tego nauczyciela
        $deck = $this->deckRepository->getDeckById($deckId);
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        if ($role !== 'admin' && $deck->getTeacherId() !== $userId) {
            $this->error('FORBIDDEN', 'To nie jest Twój zestaw', 403);
        }
        
        $this->deckRepository->assignDeckToClass($deckId, $classId);
        $this->success(['message' => 'Zestaw przypisany do klasy']);
    }
    
    /**
     * DELETE /api/classes/{classId}/decks/{deckId} - odpięcie decku od klasy
     */
    public function unassignFromClass(int $classId, int $deckId): void
    {
        $this->requireAuth();
        $this->requireMethod('DELETE');
        
        $class = $this->classRepository->getClassById($classId);
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień', 403);
        }
        
        $this->deckRepository->unassignDeckFromClass($deckId, $classId);
        $this->success(['message' => 'Zestaw odpięty od klasy']);
    }
    
    /**
     * GET /api/teacher/decks - wszystkie zestawy nauczyciela
     * POST /api/teacher/decks - tworzenie nowego zestawu
     */
    public function teacherDecks(): void
    {
        $this->requireAuth();
        
        $role = $this->getUserRole();
        if (!in_array($role, ['teacher', 'admin'])) {
            $this->error('FORBIDDEN', 'Tylko nauczyciele mogą zarządzać zestawami', 403);
        }
        
        if ($this->isPost()) {
            $this->createDeck();
            return;
        }
        
        $this->requireMethod('GET');
        
        $userId = $this->getUserId();
        $decks = $this->deckRepository->getTeacherDecks($userId);
        
        // Dodaj informację o przypisanych klasach do każdego decku
        foreach ($decks as &$deck) {
            $deck['assignedClasses'] = $this->deckRepository->getDeckClasses($deck['id']);
        }
        
        $this->success($decks);
    }

    /**
     * Tworzenie nowego decku (należy do nauczyciela)
     */
    private function createDeck(): void
    {
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if (!in_array($role, ['teacher', 'admin'])) {
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
        $classIds = $input['classIds'] ?? []; // Opcjonalne przypisanie do klas
        
        if (empty($title)) {
            $this->error('MISSING_TITLE', 'Tytuł zestawu jest wymagany', 400);
        }
        
        if (!in_array($level, ['beginner', 'intermediate', 'advanced'])) {
            $level = 'beginner';
        }
        
        // Walidacja URL obrazka (może być ścieżka z uploadu lub URL)
        if ($imageUrl && !str_starts_with($imageUrl, '/public/') && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        }
        
        try {
            $deckId = $this->deckRepository->createDeck($userId, $title, $description, $level, $imageUrl, $isPublic);
            
            // Przypisz do klas jeśli podano
            if (!empty($classIds)) {
                foreach ($classIds as $classId) {
                    // Sprawdź czy nauczyciel jest właścicielem klasy
                    $class = $this->classRepository->getClassById((int) $classId);
                    if ($class && ($role === 'admin' || $class->getTeacherId() === $userId)) {
                        $this->deckRepository->assignDeckToClass($deckId, (int) $classId);
                    }
                }
            }
            
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
        
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        // Sprawdź dostęp - właściciel, admin, lub student przypisany do klasy z tym deckiem
        $hasAccess = $role === 'admin' || $deck->getTeacherId() === $userId;
        
        if (!$hasAccess) {
            // Sprawdź czy student ma dostęp przez przypisanie do klasy
            $deckClasses = $this->deckRepository->getDeckClasses($deckId);
            foreach ($deckClasses as $class) {
                if ($this->classRepository->hasAccessToClass($class['id'], $userId, $role)) {
                    $hasAccess = true;
                    break;
                }
            }
        }
        
        // Publiczne decki są dostępne dla wszystkich
        if (!$hasAccess && !$deck->isPublic()) {
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
        
        $deckArray = $deck->toArray();
        $deckArray['assignedClasses'] = $this->deckRepository->getDeckClasses($deckId);
        $this->success($deckArray);
    }

    /**
     * Aktualizacja decku
     */
    private function updateDeck($deck): void
    {
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        // Tylko właściciel lub admin może edytować
        if ($role !== 'admin' && $deck->getTeacherId() !== $userId) {
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
        
        // Walidacja URL obrazka (może być ścieżka z uploadu lub URL)
        if ($imageUrl && !str_starts_with($imageUrl, '/public/') && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
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
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        // Tylko właściciel lub admin może usunąć
        if ($role !== 'admin' && $deck->getTeacherId() !== $userId) {
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
        
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        // Sprawdź dostęp: właściciel, admin, lub student przypisany do klasy z tym deckiem
        $hasAccess = $role === 'admin' || $deck->getTeacherId() === $userId;
        
        if (!$hasAccess) {
            // Sprawdź czy student ma dostęp przez przypisanie do klasy
            $deckClasses = $this->deckRepository->getDeckClasses($deckId);
            foreach ($deckClasses as $class) {
                if ($this->classRepository->hasAccessToClass($class['id'], $userId, $role)) {
                    $hasAccess = true;
                    break;
                }
            }
        }
        
        // Publiczne decki są dostępne dla wszystkich
        if (!$hasAccess && !$deck->isPublic()) {
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
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        // Tylko właściciel decku lub admin może tworzyć karty
        if ($role !== 'admin' && $deck->getTeacherId() !== $userId) {
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
    
    /**
     * POST /api/upload/deck-image - upload obrazka okładki dla zestawu
     */
    public function uploadDeckImage(): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        // Sprawdź czy użytkownik jest nauczycielem lub adminem
        $role = $this->getUserRole();
        if (!in_array($role, ['teacher', 'admin'])) {
            $this->error('FORBIDDEN', 'Tylko nauczyciele mogą dodawać obrazki', 403);
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->error('NO_FILE', 'Nie przesłano pliku lub wystąpił błąd', 400);
        }
        
        $file = $_FILES['image'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Walidacja rozmiaru
        if ($file['size'] > $maxSize) {
            $this->error('FILE_TOO_LARGE', 'Plik jest za duży. Maksymalny rozmiar: 2MB', 400);
        }
        
        // Walidacja typu MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $this->error('INVALID_TYPE', 'Nieprawidłowy format pliku. Dozwolone: JPG, PNG, GIF, WEBP', 400);
        }
        
        // Pobierz rozszerzenie z nazwy pliku
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            $this->error('INVALID_EXTENSION', 'Nieprawidłowe rozszerzenie pliku', 400);
        }
        
        // Generuj unikalną nazwę pliku
        $filename = uniqid('deck_') . '_' . time() . '.' . $extension;
        
        // Folder docelowy
        $uploadDir = __DIR__ . '/../../public/images/decks/';
        
        // Utwórz folder jeśli nie istnieje
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $targetPath = $uploadDir . $filename;
        
        // Przenieś plik
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            error_log("Failed to move uploaded file to: " . $targetPath);
            $this->error('UPLOAD_FAILED', 'Nie udało się zapisać pliku', 500);
        }
        
        // Zwróć ścieżkę do pliku (relatywną do public)
        $publicPath = '/public/images/decks/' . $filename;
        
        $this->success(['path' => $publicPath]);
    }
    
    /**
     * PUT /api/decks/{deckId}/assign - przypisuje deck do wielu klas
     */
    public function assignToClasses(int $deckId): void
    {
        $this->requireAuth();
        $this->requireMethod('PUT');
        
        $deck = $this->deckRepository->getDeckById($deckId);
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        // Tylko właściciel lub admin
        if ($role !== 'admin' && $deck->getTeacherId() !== $userId) {
            $this->error('FORBIDDEN', 'Brak uprawnień', 403);
        }
        
        $input = $this->getJsonInput();
        $classIds = $input['classIds'] ?? [];
        
        if (!is_array($classIds)) {
            $this->error('INVALID_DATA', 'classIds musi być tablicą', 400);
        }
        
        // Pobierz aktualne przypisania
        $currentClasses = $this->deckRepository->getDeckClasses($deckId);
        $currentClassIds = array_column($currentClasses, 'id');
        
        // Dodaj nowe przypisania
        foreach ($classIds as $classId) {
            $classId = (int) $classId;
            if (!in_array($classId, $currentClassIds)) {
                // Sprawdź czy nauczyciel jest właścicielem klasy
                $class = $this->classRepository->getClassById($classId);
                if ($class && ($role === 'admin' || $class->getTeacherId() === $userId)) {
                    $this->deckRepository->assignDeckToClass($deckId, $classId);
                }
            }
        }
        
        // Usuń stare przypisania które nie są w nowej liście
        foreach ($currentClassIds as $currentClassId) {
            if (!in_array($currentClassId, $classIds)) {
                $this->deckRepository->unassignDeckFromClass($deckId, $currentClassId);
            }
        }
        
        $updatedClasses = $this->deckRepository->getDeckClasses($deckId);
        $this->success(['assignedClasses' => $updatedClasses]);
    }
}
