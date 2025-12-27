<?php

require_once 'ApiController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

/**
 * Kontroler API dla operacji autoryzacji
 */
class AuthApiController extends ApiController
{
    private const MAX_EMAIL_LENGTH = 150;
    private const MAX_PASSWORD_LENGTH = 72;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;

    private UserRepository $userRepository;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = UserRepository::getInstance();
    }

    /**
     * POST /api/auth/register
     */
    public function register(): void
    {
        $this->requireMethod('POST');
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirmPassword'] ?? '';
        $firstName = trim($input['firstName'] ?? '');
        $lastName = trim($input['lastName'] ?? '');
        $role = $input['role'] ?? 'student'; // Domyślnie student

        // Walidacja: czy pola są wypełnione
        if (empty($email) || empty($password) || empty($confirmPassword) || empty($firstName) || empty($lastName)) {
            $this->error('MISSING_FIELDS', 'Wypełnij wszystkie pola', 400);
        }

        // Walidacja: długość email
        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            $this->error('EMAIL_TOO_LONG', 'Email jest zbyt długi', 400);
        }

        // Walidacja: format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('INVALID_EMAIL', 'Nieprawidłowy format email', 400);
        }

        // Walidacja: imię
        if (!$this->isValidName($firstName)) {
            $this->error('INVALID_FIRST_NAME', 'Imię musi mieć od 2 do 100 znaków i zawierać tylko litery', 400);
        }

        // Walidacja: nazwisko
        if (!$this->isValidName($lastName)) {
            $this->error('INVALID_LAST_NAME', 'Nazwisko musi mieć od 2 do 100 znaków i zawierać tylko litery', 400);
        }

        // Walidacja: hasło
        $passwordErrors = $this->validatePassword($password);
        if (!empty($passwordErrors)) {
            $this->error('INVALID_PASSWORD', implode('. ', $passwordErrors), 400);
        }

        // Walidacja: potwierdzenie hasła
        if ($password !== $confirmPassword) {
            $this->error('PASSWORD_MISMATCH', 'Hasła nie są identyczne', 400);
        }

        // Walidacja: dozwolone role przy rejestracji (tylko student/teacher)
        if (!in_array($role, ['student', 'teacher'])) {
            $role = 'student';
        }

        // Sprawdzenie czy email jest już w bazie
        $existingUser = $this->userRepository->getUserByEmail($email);
        if ($existingUser) {
            $this->error('EMAIL_EXISTS', 'Podany adres email jest już zajęty', 409);
        }

        // Hashowanie hasła
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $userId = $this->userRepository->createUserWithRole($email, $hashedPassword, $firstName, $lastName, $role);
            
            $this->success([
                'id' => $userId,
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'role' => $role
            ], 201);
        } catch (Exception $e) {
            error_log("Registration API error: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Wystąpił błąd podczas rejestracji', 500);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(): void
    {
        $this->requireMethod('POST');
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        // Walidacja: czy pola są wypełnione
        if (empty($email) || empty($password)) {
            $this->error('MISSING_FIELDS', 'Wypełnij wszystkie pola', 400);
        }

        // Walidacja: długość
        if (strlen($email) > self::MAX_EMAIL_LENGTH || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $this->error('INVALID_CREDENTIALS', 'Nieprawidłowy email lub hasło', 401);
        }

        // Walidacja: format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('INVALID_CREDENTIALS', 'Nieprawidłowy email lub hasło', 401);
        }

        $user = $this->userRepository->getUserByEmail($email);

        // Bezpieczeństwo: nie zdradzamy czy email istnieje
        if (!$user || !password_verify($password, $user->getPassword())) {
            $this->error('INVALID_CREDENTIALS', 'Nieprawidłowy email lub hasło', 401);
        }

        // Sprawdź czy konto jest aktywne
        if (!$user->isEnabled()) {
            $this->error('ACCOUNT_DISABLED', 'Konto zostało zablokowane', 403);
        }

        // Ustaw sesję
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_role'] = $user->getRole();
        $_SESSION['user_first_name'] = $user->getFirstName();
        $_SESSION['user_last_name'] = $user->getLastName();

        $this->success([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole()
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        $this->requireMethod('POST');
        
        session_unset();
        session_destroy();

        $this->success(['message' => 'Wylogowano pomyślnie']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(): void
    {
        $this->requireMethod('GET');
        $this->requireAuth();

        $user = $this->userRepository->getUserById($this->getUserId());
        
        if (!$user) {
            session_unset();
            session_destroy();
            $this->error('USER_NOT_FOUND', 'Użytkownik nie istnieje', 404);
        }

        $this->success([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'role' => $user->getRole(),
            'bio' => $user->getBio(),
            'enabled' => $user->isEnabled()
        ]);
    }
    
    /**
     * POST /api/auth/password - zmiana hasła
     */
    public function changePassword(): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        
        $currentPassword = $input['currentPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            $this->error('MISSING_FIELDS', 'Wypełnij wszystkie pola', 400);
        }
        
        $user = $this->userRepository->getUserById($this->getUserId());
        
        if (!$user) {
            $this->error('USER_NOT_FOUND', 'Użytkownik nie istnieje', 404);
        }
        
        // Weryfikacja aktualnego hasła
        if (!password_verify($currentPassword, $user->getPassword())) {
            $this->error('INVALID_PASSWORD', 'Aktualne hasło jest nieprawidłowe', 400);
        }
        
        // Walidacja nowego hasła
        $passwordErrors = $this->validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            $this->error('INVALID_PASSWORD', implode('. ', $passwordErrors), 400);
        }
        
        // Hashowanie i zapis
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        try {
            $this->userRepository->updatePassword($this->getUserId(), $hashedPassword);
            $this->success(['message' => 'Hasło zostało zmienione']);
        } catch (Exception $e) {
            $this->error('SERVER_ERROR', 'Błąd zmiany hasła', 500);
        }
    }
    
    /**
     * POST /api/auth/profile - aktualizacja profilu
     */
    public function updateProfile(): void
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        
        $firstName = trim($input['firstname'] ?? '');
        $lastName = trim($input['lastname'] ?? '');
        $bio = trim($input['bio'] ?? '');
        
        if (empty($firstName) || empty($lastName)) {
            $this->error('MISSING_FIELDS', 'Imię i nazwisko są wymagane', 400);
        }
        
        if (!$this->isValidName($firstName)) {
            $this->error('INVALID_FIRST_NAME', 'Imię musi mieć od 2 do 100 znaków i zawierać tylko litery', 400);
        }
        
        if (!$this->isValidName($lastName)) {
            $this->error('INVALID_LAST_NAME', 'Nazwisko musi mieć od 2 do 100 znaków i zawierać tylko litery', 400);
        }
        
        try {
            $this->userRepository->updateProfile($this->getUserId(), $firstName, $lastName, $bio);
            
            // Aktualizuj sesję
            $_SESSION['user_first_name'] = $firstName;
            $_SESSION['user_last_name'] = $lastName;
            
            $this->success(['message' => 'Profil został zaktualizowany']);
        } catch (Exception $e) {
            $this->error('SERVER_ERROR', 'Błąd aktualizacji profilu', 500);
        }
    }

    private function isValidName(string $name): bool
    {
        $length = mb_strlen($name);
        return $length >= self::MIN_NAME_LENGTH && 
               $length <= self::MAX_NAME_LENGTH && 
               preg_match('/^[\p{L}\s\-]+$/u', $name);
    }

    private function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Hasło musi mieć minimum ' . self::MIN_PASSWORD_LENGTH . ' znaków';
        }
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $errors[] = 'Hasło może mieć maksimum ' . self::MAX_PASSWORD_LENGTH . ' znaków';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Hasło musi zawierać wielką literę';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Hasło musi zawierać małą literę';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Hasło musi zawierać cyfrę';
        }
        
        return $errors;
    }
}
