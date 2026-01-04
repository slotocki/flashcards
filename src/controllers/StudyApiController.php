<?php

require_once 'ApiController.php';
require_once __DIR__ . '/../repository/ProgressRepository.php';
require_once __DIR__ . '/../repository/DeckRepository.php';
require_once __DIR__ . '/../repository/ClassRepository.php';

/**
 * Kontroler API dla trybu nauki i progresu
 */
class StudyApiController extends ApiController
{
    private ProgressRepository $progressRepository;
    private DeckRepository $deckRepository;
    private ClassRepository $classRepository;

    public function __construct()
    {
        parent::__construct();
        $this->progressRepository = ProgressRepository::getInstance();
        $this->deckRepository = DeckRepository::getInstance();
        $this->classRepository = ClassRepository::getInstance();
    }

    /**
     * GET /api/study/next?deckId=... - pobiera następną kartę do nauki
     */
    public function next(): void
    {
        $this->requireMethod('GET');
        $this->requireAuth();
        
        $deckId = isset($_GET['deckId']) ? (int) $_GET['deckId'] : null;
        
        if (!$deckId) {
            $this->error('MISSING_DECK_ID', 'Wymagany parametr deckId', 400);
        }
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        // Sprawdź dostęp - publiczne decki są dostępne dla wszystkich zalogowanych
        $hasAccess = false;
        
        if ($deck->isPublic()) {
            // Publiczny deck - dostęp dla wszystkich zalogowanych
            $hasAccess = true;
        } else {
            // Prywatny deck - sprawdź dostęp do klasy
            $classId = $deck->getClassId();
            $hasAccess = $this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole());
        }
        
        if (!$hasAccess) {
            $this->error('FORBIDDEN', 'Brak dostępu do tego zestawu', 403);
        }
        
        $card = $this->progressRepository->getNextCardForStudy($this->getUserId(), $deckId);
        
        if (!$card) {
            $this->success([
                'card' => null,
                'message' => 'Brak kart do nauki w tym zestawie'
            ]);
            return;
        }
        
        // Pobierz progres dla tej karty
        $progress = $this->progressRepository->getOrCreateProgress($this->getUserId(), $card['id']);
        
        $this->success([
            'card' => [
                'id' => (int) $card['id'],
                'front' => $card['front'],
                'back' => $card['back'],
                'imagePath' => $card['image_path'] ?? null
            ],
            'progress' => [
                'status' => $progress['status'],
                'correctStreak' => (int) $progress['correct_streak'],
                'wrongStreak' => (int) $progress['wrong_streak']
            ],
            'deckTitle' => $deck->getTitle()
        ]);
    }

    /**
     * POST /api/progress/answer - zapisuje odpowiedź (Wiem/Nie wiem)
     */
    public function answer(): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $cardId = isset($input['cardId']) ? (int) $input['cardId'] : null;
        $answer = $input['answer'] ?? '';
        
        if (!$cardId) {
            $this->error('MISSING_CARD_ID', 'Wymagany parametr cardId', 400);
        }
        
        if (!in_array($answer, ['know', 'dont_know'])) {
            $this->error('INVALID_ANSWER', 'Odpowiedź musi być "know" lub "dont_know"', 400);
        }
        
        // Sprawdź czy karta istnieje
        $card = $this->deckRepository->getCardById($cardId);
        
        if (!$card) {
            $this->error('NOT_FOUND', 'Fiszka nie istnieje', 404);
        }
        
        // Sprawdź dostęp - publiczne decki są dostępne dla wszystkich zalogowanych
        $deck = $this->deckRepository->getDeckById($card->getDeckId());
        $hasAccess = false;
        
        if ($deck && $deck->isPublic()) {
            $hasAccess = true;
        } else {
            $classId = $this->deckRepository->getClassIdForDeck($card->getDeckId());
            $hasAccess = $this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole());
        }
        
        if (!$hasAccess) {
            $this->error('FORBIDDEN', 'Brak dostępu do tej fiszki', 403);
        }
        
        $correct = ($answer === 'know');
        
        try {
            $this->progressRepository->recordAnswer($this->getUserId(), $cardId, $correct);
            
            // Pobierz zaktualizowany progres
            $progress = $this->progressRepository->getOrCreateProgress($this->getUserId(), $cardId);
            
            $this->success([
                'message' => $correct ? 'Świetnie! Odpowiedź zapisana.' : 'Nie szkodzi, spróbuj ponownie!',
                'progress' => [
                    'status' => $progress['status'],
                    'correctStreak' => (int) $progress['correct_streak'],
                    'wrongStreak' => (int) $progress['wrong_streak']
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error recording answer: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas zapisywania odpowiedzi', 500);
        }
    }

    /**
     * GET /api/progress/stats - ogólne statystyki użytkownika
     */
    public function stats(): void
    {
        $this->requireMethod('GET');
        $this->requireAuth();
        
        $userId = $this->getUserId();
        
        $stats = $this->progressRepository->getUserStats($userId);
        $deckProgress = $this->progressRepository->getUserProgressByDecks($userId);
        
        $this->success([
            'overall' => [
                'totalStudied' => (int) ($stats['total_studied'] ?? 0),
                'totalKnown' => (int) ($stats['total_known'] ?? 0),
                'totalLearning' => (int) ($stats['total_learning'] ?? 0)
            ],
            'byDeck' => array_map(function($dp) {
                return [
                    'deckId' => (int) $dp['deck_id'],
                    'deckTitle' => $dp['deck_title'],
                    'className' => $dp['class_name'],
                    'totalCards' => (int) $dp['total_cards'],
                    'knownCards' => (int) $dp['known_cards'],
                    'learningCards' => (int) $dp['learning_cards'],
                    'progress' => $dp['total_cards'] > 0 
                        ? round(($dp['known_cards'] / $dp['total_cards']) * 100, 1) 
                        : 0
                ];
            }, $deckProgress)
        ]);
    }

    /**
     * GET /api/progress/deck/{deckId} - progres dla konkretnego decku
     */
    public function deckProgress(int $deckId): void
    {
        $this->requireMethod('GET');
        $this->requireAuth();
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        // Sprawdź dostęp - publiczne decki są dostępne dla wszystkich zalogowanych
        $hasAccess = false;
        
        if ($deck->isPublic()) {
            $hasAccess = true;
        } else {
            $classId = $deck->getClassId();
            $hasAccess = $this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole());
        }
        
        if (!$hasAccess) {
            $this->error('FORBIDDEN', 'Brak dostępu do tego zestawu', 403);
        }
        
        $progress = $this->progressRepository->getDeckProgress($this->getUserId(), $deckId);
        
        $totalCards = (int) ($progress['total_cards'] ?? 0);
        $knownCards = (int) ($progress['known_cards'] ?? 0);
        
        $this->success([
            'deckId' => $deckId,
            'deckTitle' => $deck->getTitle(),
            'totalCards' => $totalCards,
            'knownCards' => $knownCards,
            'learningCards' => (int) ($progress['learning_cards'] ?? 0),
            'newCards' => (int) ($progress['new_cards'] ?? 0),
            'progress' => $totalCards > 0 ? round(($knownCards / $totalCards) * 100, 1) : 0
        ]);
    }
    
    /**
     * POST /api/progress/reset/{deckId} - resetuje progres dla zestawu
     */
    public function resetProgress(int $deckId): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Zestaw nie istnieje', 404);
        }
        
        // Sprawdź dostęp
        $hasAccess = false;
        
        if ($deck->isPublic()) {
            $hasAccess = true;
        } else {
            $classId = $deck->getClassId();
            $hasAccess = $this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole());
        }
        
        if (!$hasAccess) {
            $this->error('FORBIDDEN', 'Brak dostępu do tego zestawu', 403);
        }
        
        try {
            $this->progressRepository->resetDeckProgress($this->getUserId(), $deckId);
            $this->success(['message' => 'Progres został zresetowany']);
        } catch (Exception $e) {
            error_log("Error resetting progress: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas resetowania progresu', 500);
        }
    }
}
