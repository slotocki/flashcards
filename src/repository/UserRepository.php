<?php

require_once 'Repository.php';
require_once __DIR__ . '/../model/User.php';

class UserRepository extends Repository
{

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
            $user['lastname']
        );
    }
}