<?php

require_once 'ApiController.php';
require_once __DIR__ . '/../repository/DeckRepository.php';
require_once __DIR__ . '/../repository/ProgressRepository.php';

/**
 * Kontroler API dla funkcjonalności Community
 */
class CommunityApiController extends ApiController
{
    private DeckRepository $deckRepository;
    private ProgressRepository $progressRepository;

    public function __construct()
    {
        parent::__construct();
        $this->deckRepository = DeckRepository::getInstance();
        $this->progressRepository = ProgressRepository::getInstance();
    }

    /**
     * GET /api/community/decks - Pobiera publiczne decki
     */
    public function getPublicDecks(): void
    {
        $this->requireMethod('GET');
        
        $search = $_GET['search'] ?? '';
        $sortBy = $_GET['sort'] ?? 'popular';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $decks = $this->deckRepository->getPublicDecks($search, $sortBy, $limit, $offset);
        $total = $this->deckRepository->countPublicDecks($search);
        
        // Dodaj info o subskrypcji jeśli zalogowany
        if ($this->isAuthenticated()) {
            $userId = $this->getUserId();
            foreach ($decks as &$deck) {
                $deck['isSubscribed'] = $this->deckRepository->isSubscribed($userId, $deck['id']);
                $deck['userRating'] = $this->deckRepository->getUserRating($userId, $deck['id']);
            }
        }
        
        $this->success([
            'decks' => $decks,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * GET /api/community/subscribed - Pobiera subskrybowane decki użytkownika
     */
    public function getSubscribedDecks(): void
    {
        $this->requireMethod('GET');
        $this->requireAuth();
        
        $userId = $this->getUserId();
        $decks = $this->deckRepository->getSubscribedDecks($userId);
        
        // Dodaj progress dla każdego decku
        foreach ($decks as &$deck) {
            $deck['userRating'] = $this->deckRepository->getUserRating($userId, $deck['id']);
            $progress = $this->progressRepository->getDeckProgress($userId, $deck['id']);
            $deck['progress'] = $progress;
        }
        
        $this->success(['decks' => $decks]);
    }

    /**
     * GET /api/community/deck/{id} - Pobiera szczegóły publicznego decku
     */
    public function getDeckDetails(int $deckId): void
    {
        $this->requireMethod('GET');
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Deck nie został znaleziony', 404);
        }
        
        if (!$deck->isPublic()) {
            $this->error('FORBIDDEN', 'Ten deck nie jest publiczny', 403);
        }
        
        $deckData = $deck->toArray();
        $deckData['cards'] = $this->deckRepository->getCardsByDeckId($deckId);
        
        if ($this->isAuthenticated()) {
            $userId = $this->getUserId();
            $deckData['isSubscribed'] = $this->deckRepository->isSubscribed($userId, $deckId);
            $deckData['userRating'] = $this->deckRepository->getUserRating($userId, $deckId);
            $deckData['progress'] = $this->progressRepository->getDeckProgress($userId, $deckId);
        }
        
        // Zwiększ licznik wyświetleń
        $this->deckRepository->incrementViewsCount($deckId);
        
        $this->success($deckData);
    }

    /**
     * GET /api/community/share/{token} - Pobiera deck po tokenie udostępniania
     */
    public function getDeckByShareToken(string $token): void
    {
        $this->requireMethod('GET');
        
        $deck = $this->deckRepository->getDeckByShareToken($token);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Deck nie został znaleziony lub link wygasł', 404);
        }
        
        $deckData = $deck->toArray();
        $deckData['cards'] = $this->deckRepository->getCardsByDeckId($deck->getId());
        
        if ($this->isAuthenticated()) {
            $userId = $this->getUserId();
            $deckData['isSubscribed'] = $this->deckRepository->isSubscribed($userId, $deck->getId());
            $deckData['userRating'] = $this->deckRepository->getUserRating($userId, $deck->getId());
        }
        
        $this->success($deckData);
    }

    /**
     * POST /api/community/deck/{id}/subscribe - Subskrybuje deck
     */
    public function subscribeToDeck(int $deckId): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        $deck = $this->deckRepository->getDeckById($deckId);
        
        if (!$deck) {
            $this->error('NOT_FOUND', 'Deck nie został znaleziony', 404);
        }
        
        if (!$deck->isPublic()) {
            $this->error('FORBIDDEN', 'Nie można subskrybować prywatnego decku', 403);
        }
        
        $userId = $this->getUserId();
        $result = $this->deckRepository->subscribeToDeck($userId, $deckId);
        
        if ($result) {
            $this->success(['message' => 'Subskrypcja dodana pomyślnie']);
        } else {
            $this->error('SUBSCRIBE_FAILED', 'Nie udało się dodać subskrypcji', 500);
        }
    }

    /**
     * DELETE /api/community/deck/{id}/subscribe - Usuwa subskrypcję
     */
    public function unsubscribeFromDeck(int $deckId): void
    {
        $this->requireMethod('DELETE');
        $this->requireAuth();
        
        $userId = $this->getUserId();
        $result = $this->deckRepository->unsubscribeFromDeck($userId, $deckId);
        
        if ($result) {
            $this->success(['message' => 'Subskrypcja usunięta pomyślnie']);
        } else {
            $this->error('UNSUBSCRIBE_FAILED', 'Nie udało się usunąć subskrypcji', 500);
        }
    }

    /**
     * POST /api/community/deck/{id}/rate - Ocenia deck
     */
    public function rateDeck(int $deckId): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        if (!$input || !isset($input['rating'])) {
            $this->error('INVALID_INPUT', 'Wymagane pole: rating (1-5)', 400);
        }
        
        $rating = (int) $input['rating'];
        if ($rating < 1 || $rating > 5) {
            $this->error('INVALID_RATING', 'Ocena musi być w zakresie 1-5', 400);
        }
        
        $deck = $this->deckRepository->getDeckById($deckId);
        if (!$deck) {
            $this->error('NOT_FOUND', 'Deck nie został znaleziony', 404);
        }
        
        if (!$deck->isPublic()) {
            $this->error('FORBIDDEN', 'Nie można ocenić prywatnego decku', 403);
        }
        
        $userId = $this->getUserId();
        $result = $this->deckRepository->rateDeck($userId, $deckId, $rating);
        
        if ($result) {
            // Pobierz zaktualizowane statystyki
            $updatedDeck = $this->deckRepository->getDeckById($deckId);
            $this->success([
                'message' => 'Ocena zapisana pomyślnie',
                'averageRating' => $updatedDeck->getAverageRating(),
                'ratingsCount' => $updatedDeck->getRatingsCount()
            ]);
        } else {
            $this->error('RATE_FAILED', 'Nie udało się zapisać oceny', 500);
        }
    }
}
