<?php

require_once 'Repository.php';
require_once __DIR__ . '/../model/User.php';

class UserRepository extends Repository
{
    private static ?UserRepository $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): UserRepository
    {
        if (self::$instance === null) {
            self::$instance = new UserRepository();
        }
        return self::$instance;
    }

    public function getUsers(): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM users
        ');
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $users;
    }

    public function createUser(string $email, string $hashedPassword, string $firstName, string $lastName): void 
    {
        try {
            $stmt = $this->database->connect()->prepare('
                INSERT INTO users (email, password, firstname, lastname) 
                VALUES (:email, :password, :firstname, :lastname)
            ');

            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':firstname', $firstName, PDO::PARAM_STR);
            $stmt->bindParam(':lastname', $lastName, PDO::PARAM_STR);
            
            $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error creating user: " . $e->getMessage());
        }
    }

    /**
     * Tworzy użytkownika z określoną rolą
     */
    public function createUserWithRole(string $email, string $hashedPassword, string $firstName, string $lastName, string $role = 'student'): int 
    {
        try {
            $conn = $this->database->connect();
            $stmt = $conn->prepare('
                INSERT INTO users (email, password, firstname, lastname, role) 
                VALUES (:email, :password, :firstname, :lastname, :role)
                RETURNING id
            ');

            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':firstname', $firstName, PDO::PARAM_STR);
            $stmt->bindParam(':lastname', $lastName, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['id'];
        } catch (PDOException $e) {
            throw new Exception("Error creating user: " . $e->getMessage());
        }
    }

    public function getUserByEmail(string $email): ?User
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM users WHERE email = :email
        ');
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return null;
        }

        return new User(
            $user['id'],
            $user['email'],
            $user['password'],
            $user['firstname'],
            $user['lastname'],
            $user['role'] ?? 'student',
            $user['enabled'] ?? true,
            $user['bio'] ?? null
        );
    }

    public function getUserById(int $id): ?User
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM users WHERE id = :id
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return null;
        }

        return new User(
            $user['id'],
            $user['email'],
            $user['password'],
            $user['firstname'],
            $user['lastname'],
            $user['role'] ?? 'student',
            $user['enabled'] ?? true,
            $user['bio'] ?? null
        );
    }

    /**
     * Aktualizuje rolę użytkownika
     */
    public function updateUserRole(int $userId, string $role): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                UPDATE users SET role = :role WHERE id = :id
            ');
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating user role: " . $e->getMessage());
        }
    }

    /**
     * Włącza/wyłącza konto użytkownika
     */
    public function setUserEnabled(int $userId, bool $enabled): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                UPDATE users SET enabled = :enabled WHERE id = :id
            ');
            $stmt->bindParam(':enabled', $enabled, PDO::PARAM_BOOL);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating user status: " . $e->getMessage());
        }
    }

    /**
     * Pobiera wszystkich użytkowników (dla admina)
     */
    public function getAllUsers(): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, email, firstname, lastname, role, enabled, bio FROM users ORDER BY id
        ');
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($user) {
            return new User(
                $user['id'],
                $user['email'],
                '',
                $user['firstname'],
                $user['lastname'],
                $user['role'] ?? 'student',
                $user['enabled'] ?? true,
                $user['bio'] ?? null
            );
        }, $users);
    }
    
    /**
     * Aktualizuje hasło użytkownika
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                UPDATE users SET password = :password WHERE id = :id
            ');
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating password: " . $e->getMessage());
        }
    }
    
    /**
     * Aktualizuje profil użytkownika
     */
    public function updateProfile(int $userId, string $firstName, string $lastName, string $bio): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                UPDATE users SET firstname = :firstname, lastname = :lastname, bio = :bio WHERE id = :id
            ');
            $stmt->bindParam(':firstname', $firstName, PDO::PARAM_STR);
            $stmt->bindParam(':lastname', $lastName, PDO::PARAM_STR);
            $stmt->bindParam(':bio', $bio, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating profile: " . $e->getMessage());
        }
    }
}