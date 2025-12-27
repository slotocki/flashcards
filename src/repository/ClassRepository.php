<?php

require_once 'Repository.php';
require_once __DIR__ . '/../model/SchoolClass.php';

class ClassRepository extends Repository
{
    private static ?ClassRepository $instance = null;

    public static function getInstance(): ClassRepository
    {
        if (self::$instance === null) {
            self::$instance = new ClassRepository();
        }
        return self::$instance;
    }

    /**
     * Generuje unikalny kod dołączenia
     */
    private function generateJoinCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Tworzy nową klasę
     */
    public function createClass(int $teacherId, string $name, ?string $description = null, ?string $language = null): int
    {
        $conn = $this->database->connect();
        
        // Generuj unikalny kod
        $joinCode = $this->generateJoinCode();
        while ($this->getClassByJoinCode($joinCode) !== null) {
            $joinCode = $this->generateJoinCode();
        }
        
        $stmt = $conn->prepare('
            INSERT INTO classes (teacher_id, name, description, join_code, language)
            VALUES (:teacher_id, :name, :description, :join_code, :language)
            RETURNING id
        ');
        
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':join_code', $joinCode, PDO::PARAM_STR);
        $stmt->bindParam(':language', $language, PDO::PARAM_STR);
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['id'];
    }

    /**
     * Pobiera klasę po ID
     */
    public function getClassById(int $id): ?SchoolClass
    {
        $stmt = $this->database->connect()->prepare('
            SELECT c.*, CONCAT(u.firstname, \' \', u.lastname) as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = :id
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToClass($row);
    }

    /**
     * Pobiera klasę po kodzie dołączenia
     */
    public function getClassByJoinCode(string $joinCode): ?SchoolClass
    {
        $stmt = $this->database->connect()->prepare('
            SELECT c.*, CONCAT(u.firstname, \' \', u.lastname) as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.join_code = :join_code
        ');
        $stmt->bindParam(':join_code', $joinCode, PDO::PARAM_STR);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToClass($row);
    }

    /**
     * Pobiera klasy nauczyciela
     */
    public function getClassesByTeacherId(int $teacherId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT c.*, CONCAT(u.firstname, \' \', u.lastname) as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.teacher_id = :teacher_id
            ORDER BY c.created_at DESC
        ');
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        
        $classes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[] = $this->mapRowToClass($row)->toArray();
        }
        return $classes;
    }

    /**
     * Pobiera klasy studenta
     */
    public function getClassesByStudentId(int $studentId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT c.*, CONCAT(u.firstname, \' \', u.lastname) as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            JOIN class_members cm ON c.id = cm.class_id
            WHERE cm.student_id = :student_id
            ORDER BY c.created_at DESC
        ');
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        
        $classes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[] = $this->mapRowToClass($row)->toArray();
        }
        return $classes;
    }

    /**
     * Sprawdza czy student jest członkiem klasy
     */
    public function isStudentInClass(int $classId, int $studentId): bool
    {
        $stmt = $this->database->connect()->prepare('
            SELECT 1 FROM class_members 
            WHERE class_id = :class_id AND student_id = :student_id
        ');
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch() !== false;
    }

    /**
     * Dodaje studenta do klasy
     */
    public function addStudentToClass(int $classId, int $studentId): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                INSERT INTO class_members (class_id, student_id)
                VALUES (:class_id, :student_id)
            ');
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            // Może rzucić wyjątek jeśli student już jest w klasie (UNIQUE constraint)
            return false;
        }
    }

    /**
     * Pobiera członków klasy
     */
    public function getClassMembers(int $classId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT u.id, u.email, u.firstname, u.lastname, cm.joined_at
            FROM users u
            JOIN class_members cm ON u.id = cm.student_id
            WHERE cm.class_id = :class_id
            ORDER BY u.lastname, u.firstname
        ');
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sprawdza czy użytkownik ma dostęp do klasy (jest nauczycielem lub studentem)
     */
    public function hasAccessToClass(int $classId, int $userId, string $role): bool
    {
        if ($role === 'admin') {
            return true;
        }
        
        if ($role === 'teacher') {
            $class = $this->getClassById($classId);
            return $class && $class->getTeacherId() === $userId;
        }
        
        if ($role === 'student') {
            return $this->isStudentInClass($classId, $userId);
        }
        
        return false;
    }
    
    /**
     * Pobiera wszystkie klasy (dla admina)
     */
    public function getAllClasses(): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT c.*, CONCAT(u.firstname, \' \', u.lastname) as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            ORDER BY c.created_at DESC
        ');
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'mapRowToClass'], $rows);
    }
    
    /**
     * Usuwa klasę
     */
    public function deleteClass(int $classId): bool
    {
        try {
            $conn = $this->database->connect();
            
            // Usuń powiązane rekordy
            $conn->prepare('DELETE FROM class_members WHERE class_id = :id')->execute([':id' => $classId]);
            $conn->prepare('DELETE FROM tasks WHERE class_id = :id')->execute([':id' => $classId]);
            
            // Usuń decki (co automatycznie usuwa karty przez CASCADE)
            $conn->prepare('DELETE FROM decks WHERE class_id = :id')->execute([':id' => $classId]);
            
            // Usuń klasę
            $stmt = $conn->prepare('DELETE FROM classes WHERE id = :id');
            $stmt->bindParam(':id', $classId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting class: " . $e->getMessage());
        }
    }
    
    /**
     * Usuwa studenta z klasy
     */
    public function removeStudentFromClass(int $classId, int $studentId): bool
    {
        try {
            $stmt = $this->database->connect()->prepare('
                DELETE FROM class_members 
                WHERE class_id = :class_id AND student_id = :student_id
            ');
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error removing student: " . $e->getMessage());
        }
    }

    private function mapRowToClass(array $row): SchoolClass
    {
        return new SchoolClass(
            (int) $row['id'],
            (int) $row['teacher_id'],
            $row['name'],
            $row['description'],
            $row['join_code'],
            $row['language'] ?? null,
            $row['created_at'] ?? null,
            $row['teacher_name'] ?? null
        );
    }
}
