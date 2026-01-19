<?php

require_once 'AppController.php';

/**
 * Bazowy kontroler dla endpointów API zwracających JSON
 */
class ApiController extends AppController
{
    public function __construct()
    {
        // Startuj sesję jeśli jeszcze nie wystartowana
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Zwraca odpowiedź JSON i kończy skrypt
     * 
     * @param mixed $data Dane do zakodowania jako JSON
     * @param int $statusCode Kod HTTP (domyślnie 200)
     */
    protected function jsonResponse($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }

    /**
     * Zwraca sukces API w formacie { "ok": true, "data": ... }
     */
    protected function success($data = null, int $statusCode = 200): void
    {
        $response = ['ok' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->jsonResponse($response, $statusCode);
    }

    /**
     * Zwraca błąd API w formacie { "ok": false, "error": { "code": ..., "message": ... } }
     */
    protected function error(string $code, string $message, int $statusCode = 400): void
    {
        $this->jsonResponse([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ], $statusCode);
    }

    /**
     * Pobiera dane JSON z body requestu
     */
    protected function getJsonInput(): ?array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (stripos($contentType, 'application/json') === false) {
            return null;
        }
        
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $decoded;
    }

    /**
     * Sprawdza czy użytkownik jest zalogowany
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Wymaga zalogowania - jeśli nie zalogowany, zwraca 401
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('UNAUTHORIZED', 'Musisz być zalogowany', 401);
        }
    }

    /**
     * Zwraca ID zalogowanego użytkownika
     */
    protected function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Zwraca rolę zalogowanego użytkownika
     */
    protected function getUserRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Sprawdza czy użytkownik ma daną rolę
     */
    protected function hasRole(string $role): bool
    {
        return $this->getUserRole() === $role;
    }

    /**
     * Wymaga określonej roli - jeśli brak, zwraca 403
     */
    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        
        $userRole = $this->getUserRole();
        if (!in_array($userRole, $roles)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do tej operacji', 403);
        }
    }

    /**
     * Sprawdza wymaganą metodę HTTP
     */
    protected function requireMethod(string ...$methods): void
    {
        $currentMethod = $_SERVER['REQUEST_METHOD'];
        if (!in_array($currentMethod, $methods)) {
            $this->error('METHOD_NOT_ALLOWED', 'Metoda niedozwolona', 405);
        }
    }
    
    /**
     * Sprawdza czy request to POST
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Sprawdza czy request to PUT
     */
    protected function isPut(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'PUT';
    }
}
