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
    
    /**
     * POST /api/auth/forgot-password - żądanie resetu hasła
     */
    public function forgotPassword(): void
    {
        $this->requireMethod('POST');
        
        $input = $this->getJsonInput();
        $email = trim($input['email'] ?? '');
        
        if (empty($email)) {
            $this->error('MISSING_EMAIL', 'Podaj adres email', 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('INVALID_EMAIL', 'Nieprawidłowy format email', 400);
        }
        
        $user = $this->userRepository->getUserByEmail($email);
        
        // Bezpieczeństwo: zawsze odpowiadamy tak samo (nie zdradzamy czy email istnieje)
        if ($user) {
            try {
                // Generuj token resetowania hasła
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Zapisz token w bazie
                $this->userRepository->savePasswordResetToken($user->getId(), $token, $expiry);
                
                // Wyślij email z linkiem do resetowania
                $this->sendPasswordResetEmail($user->getEmail(), $user->getFirstName(), $token);
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                // Nie pokazujemy błędu użytkownikowi ze względów bezpieczeństwa
            }
        }
        
        // Zawsze zwracamy sukces (bezpieczeństwo)
        $this->success(['message' => 'Jeśli podany adres email istnieje w systemie, wysłaliśmy na niego link do resetowania hasła.']);
    }
    
    /**
     * POST /api/auth/reset-password - reset hasła z tokenem
     */
    public function resetPassword(): void
    {
        $this->requireMethod('POST');
        
        $input = $this->getJsonInput();
        $token = $input['token'] ?? '';
        $newPassword = $input['password'] ?? '';
        
        if (empty($token) || empty($newPassword)) {
            $this->error('MISSING_FIELDS', 'Wypełnij wszystkie pola', 400);
        }
        
        // Walidacja nowego hasła
        $passwordErrors = $this->validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            $this->error('INVALID_PASSWORD', implode('. ', $passwordErrors), 400);
        }
        
        // Sprawdź token
        $resetData = $this->userRepository->getPasswordResetByToken($token);
        
        if (!$resetData) {
            $this->error('INVALID_TOKEN', 'Link do resetowania hasła jest nieprawidłowy lub wygasł', 400);
        }
        
        // Sprawdź czy token nie wygasł
        if (strtotime($resetData['expires_at']) < time()) {
            $this->userRepository->deletePasswordResetToken($token);
            $this->error('TOKEN_EXPIRED', 'Link do resetowania hasła wygasł. Poproś o nowy.', 400);
        }
        
        try {
            // Zmień hasło
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $this->userRepository->updatePassword($resetData['user_id'], $hashedPassword);
            
            // Usuń użyty token
            $this->userRepository->deletePasswordResetToken($token);
            
            $this->success(['message' => 'Hasło zostało zmienione. Możesz się teraz zalogować.']);
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Wystąpił błąd podczas zmiany hasła', 500);
        }
    }
    
    /**
     * Wysyła email z linkiem do resetowania hasła
     */
    private function sendPasswordResetEmail(string $email, string $firstName, string $token): bool
    {
        // Załaduj konfigurację email
        require_once __DIR__ . '/../../config.php';
        
        if (!defined('EMAIL_HOST')) {
            error_log("Email configuration not found");
            return false;
        }
        
        $resetUrl = "http://{$_SERVER['HTTP_HOST']}/reset-password?token={$token}";
        
        $subject = "Resetowanie hasła - MemoRise";
        $htmlBody = "
            <html>
            <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2196F3;'>🔐 Resetowanie hasła</h2>
                <p>Cześć {$firstName}!</p>
                <p>Otrzymaliśmy prośbę o resetowanie hasła do Twojego konta w MemoRise.</p>
                <p>Kliknij poniższy przycisk, aby ustawić nowe hasło:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}' style='background-color: #2196F3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Resetuj hasło
                    </a>
                </p>
                <p style='color: #666; font-size: 0.9em;'>Link wygaśnie za 1 godzinę.</p>
                <p style='color: #666; font-size: 0.9em;'>Jeśli to nie Ty prosiłeś o reset hasła, zignoruj tę wiadomość.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='color: #999; font-size: 0.8em;'>MemoRise - Twoja platforma do nauki fiszek</p>
            </body>
            </html>
        ";
        
        $textBody = "Cześć {$firstName}!\n\n" .
            "Otrzymaliśmy prośbę o resetowanie hasła do Twojego konta w MemoRise.\n\n" .
            "Skopiuj poniższy link do przeglądarki, aby ustawić nowe hasło:\n" .
            "{$resetUrl}\n\n" .
            "Link wygaśnie za 1 godzinę.\n\n" .
            "Jeśli to nie Ty prosiłeś o reset hasła, zignoruj tę wiadomość.\n\n" .
            "MemoRise - Twoja platforma do nauki fiszek";
        
        return $this->sendEmail($email, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Wysyła email przez SMTP z obsługą STARTTLS dla portu 587
     */
    private function sendEmail(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        try {
            $host = EMAIL_HOST;
            $port = EMAIL_PORT;
            $user = EMAIL_USER;
            $pass = EMAIL_PASS;
            $from = EMAIL_FROM;
            $fromName = EMAIL_FROM_NAME;
            
            // Dla portu 587 używamy zwykłego połączenia + STARTTLS
            // Dla portu 465 używamy SSL od początku
            if ($port == 465) {
                $socket = @fsockopen("ssl://{$host}", $port, $errno, $errstr, 30);
            } else {
                // Port 587 - zwykłe połączenie, potem STARTTLS
                $socket = @fsockopen($host, $port, $errno, $errstr, 30);
            }
            
            if (!$socket) {
                error_log("SMTP connection failed to {$host}:{$port}: {$errstr} ({$errno})");
                return false;
            }
            
            // Czytaj odpowiedź serwera
            $this->smtpRead($socket);
            
            // EHLO
            fwrite($socket, "EHLO localhost\r\n");
            $this->smtpRead($socket);
            
            // STARTTLS dla portu 587
            if ($port == 587) {
                fwrite($socket, "STARTTLS\r\n");
                $response = $this->smtpRead($socket);
                
                if (strpos($response, '220') === false) {
                    error_log("STARTTLS failed: {$response}");
                    fclose($socket);
                    return false;
                }
                
                // Upgrade do TLS
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                
                // EHLO ponownie po TLS
                fwrite($socket, "EHLO localhost\r\n");
                $this->smtpRead($socket);
            }
            
            // AUTH LOGIN
            fwrite($socket, "AUTH LOGIN\r\n");
            $this->smtpRead($socket);
            
            fwrite($socket, base64_encode($user) . "\r\n");
            $this->smtpRead($socket);
            
            fwrite($socket, base64_encode($pass) . "\r\n");
            $response = $this->smtpRead($socket);
            
            if (strpos($response, '235') === false) {
                error_log("SMTP auth failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // MAIL FROM
            fwrite($socket, "MAIL FROM: <{$from}>\r\n");
            $this->smtpRead($socket);
            
            // RCPT TO
            fwrite($socket, "RCPT TO: <{$to}>\r\n");
            $this->smtpRead($socket);
            
            // DATA
            fwrite($socket, "DATA\r\n");
            $this->smtpRead($socket);
            
            // Nagłówki i treść
            $boundary = md5(time());
            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "\r\n";
            
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $textBody . "\r\n\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            $message .= "--{$boundary}--\r\n";
            
            fwrite($socket, $headers . $message . "\r\n.\r\n");
            $this->smtpRead($socket);
            
            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            return true;
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
    
    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
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
        // Używamy Unicode property \p{Lu} dla wielkich liter (obsługuje polskie znaki)
        if (!preg_match('/\p{Lu}/u', $password)) {
            $errors[] = 'Hasło musi zawierać wielką literę';
        }
        // Używamy Unicode property \p{Ll} dla małych liter (obsługuje polskie znaki)
        if (!preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'Hasło musi zawierać małą literę';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Hasło musi zawierać cyfrę';
        }
        
        return $errors;
    }
}
