<?php

require_once 'AppController.php';
require_once __DIR__.'/../repository/UserRepository.php';

class SecurityController extends AppController
{
    // Limity długości pól
    private const MAX_EMAIL_LENGTH = 150;
    private const MAX_PASSWORD_LENGTH = 72; // bcrypt limit
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;

    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = UserRepository::getInstance();
    }

    /**
     * Waliduje format email po stronie serwera
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Waliduje złożoność hasła (min. długość, wielkie/małe litery, cyfra)
     */
    private function isValidPassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Hasło musi mieć minimum ' . self::MIN_PASSWORD_LENGTH . ' znaków';
        }
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $errors[] = 'Hasło może mieć maksimum ' . self::MAX_PASSWORD_LENGTH . ' znaków';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Hasło musi zawierać przynajmniej jedną wielką literę';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Hasło musi zawierać przynajmniej jedną małą literę';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Hasło musi zawierać przynajmniej jedną cyfrę';
        }
        
        return $errors;
    }

    /**
     * Waliduje długość i format imienia/nazwiska
     */
    private function isValidName(string $name): bool
    {
        $length = mb_strlen($name);
        return $length >= self::MIN_NAME_LENGTH && 
               $length <= self::MAX_NAME_LENGTH && 
               preg_match('/^[\p{L}\s\-]+$/u', $name);
    }
 
    public function login()
    {
        if (!$this->isPost()) {
            return $this->render('login');
        }

        $email = trim($_POST["email"] ?? '');
        $password = $_POST["password"] ?? '';

        // Walidacja: czy pola są wypełnione
        if (empty($email) || empty($password)) {
            http_response_code(400);
            return $this->render('login', ['messages' => 'Wypełnij wszystkie pola']);
        }

        // Walidacja: ograniczenie długości wejścia
        if (strlen($email) > self::MAX_EMAIL_LENGTH || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            http_response_code(400);
            return $this->render('login', ['messages' => 'Nieprawidłowe dane logowania']);
        }

        // Walidacja: format email po stronie serwera
        if (!$this->isValidEmail($email)) {
            http_response_code(400);
            return $this->render('login', ['messages' => 'Nieprawidłowy email lub hasło']);
        }

        $user = $this->userRepository->getUserByEmail($email);

        // BEZPIECZEŃSTWO: Nie zdradzamy czy email istnieje w bazie
        // Używamy tego samego komunikatu dla obu przypadków
        if (!$user || !password_verify($password, $user->getPassword())) {
            http_response_code(401);
            return $this->render('login', ['messages' => 'Nieprawidłowy email lub hasło']);
        }

        session_start();
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
        exit();
    }

    public function register()
    {
        if (!$this->isPost()) {
            return $this->render('register');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');

        // Walidacja: czy pola są wypełnione
        if (empty($email) || empty($password) || empty($confirmPassword) || empty($firstName) || empty($lastName)) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Wypełnij wszystkie pola']);
        }

        // Walidacja: ograniczenie długości wejścia
        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Email jest zbyt długi (max ' . self::MAX_EMAIL_LENGTH . ' znaków)']);
        }

        // Walidacja: format email po stronie serwera
        if (!$this->isValidEmail($email)) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Nieprawidłowy format adresu email']);
        }

        // Walidacja: imię
        if (!$this->isValidName($firstName)) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Imię musi mieć od ' . self::MIN_NAME_LENGTH . ' do ' . self::MAX_NAME_LENGTH . ' znaków i zawierać tylko litery']);
        }

        // Walidacja: nazwisko
        if (!$this->isValidName($lastName)) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Nazwisko musi mieć od ' . self::MIN_NAME_LENGTH . ' do ' . self::MAX_NAME_LENGTH . ' znaków i zawierać tylko litery']);
        }

        // Walidacja: złożoność hasła
        $passwordErrors = $this->isValidPassword($password);
        if (!empty($passwordErrors)) {
            http_response_code(400);
            return $this->render('register', ['messages' => implode('<br>', $passwordErrors)]);
        }

        // Walidacja: potwierdzenie hasła
        if ($password !== $confirmPassword) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Hasła nie są identyczne']);
        }

        // Sprawdzenie czy email jest już w bazie
        $existingUser = $this->userRepository->getUserByEmail($email);
        if ($existingUser) {
            http_response_code(409);
            return $this->render('register', ['messages' => 'Podany adres email jest już zajęty']);
        }

        // Hashowanie hasła z użyciem bcrypt (PASSWORD_BCRYPT) lub Argon2
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $this->userRepository->createUser($email, $hashedPassword, $firstName, $lastName);
            
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login?registered=1");
            exit();
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            http_response_code(500);
            return $this->render('register', ['messages' => 'Wystąpił błąd podczas rejestracji. Spróbuj ponownie.']);
        }
    }

    public function logout()
    {
        session_start();
        session_unset();
        session_destroy();

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
        exit();
    }
}