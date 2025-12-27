<?php

require_once 'Repository.php';
require_once __DIR__ . '/../model/Task.php';

class TaskRepository extends Repository
{
    private static ?TaskRepository $instance = null;

    public static function getInstance(): TaskRepository
    {
        if (self::$instance === null) {
            self::$instance = new TaskRepository();
        }
        return self::$instance;
    }

    /**
     * Tworzy nowe zadanie
     */
    public function createTask(int $classId, string $title, ?string $description = null, ?int $deckId = null, ?string $dueDate = null): int
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO tasks (class_id, deck_id, title, description, due_date)
            VALUES (:class_id, :deck_id, :title, :description, :due_date)
            RETURNING id
        ');
        
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':deck_id', $deckId, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':due_date', $dueDate, PDO::PARAM_STR);
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['id'];
    }

    /**
     * Pobiera zadania dla klasy
     */
    public function getTasksByClassId(int $classId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT t.*, d.title as deck_title
            FROM tasks t
            LEFT JOIN decks d ON t.deck_id = d.id
            WHERE t.class_id = :class_id
            ORDER BY t.due_date ASC NULLS LAST, t.created_at DESC
        ');
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $this->mapRowToTask($row)->toArray();
        }
        return $tasks;
    }

    /**
     * Pobiera zadanie po ID
     */
    public function getTaskById(int $id): ?Task
    {
        $stmt = $this->database->connect()->prepare('
            SELECT t.*, d.title as deck_title
            FROM tasks t
            LEFT JOIN decks d ON t.deck_id = d.id
            WHERE t.id = :id
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToTask($row);
    }

    /**
     * Usuwa zadanie
     */
    public function deleteTask(int $id): bool
    {
        $stmt = $this->database->connect()->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Pobiera nadchodzące zadania dla studenta
     */
    public function getUpcomingTasksForStudent(int $studentId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT t.*, d.title as deck_title, c.name as class_name
            FROM tasks t
            JOIN classes c ON t.class_id = c.id
            JOIN class_members cm ON c.id = cm.class_id AND cm.student_id = :student_id
            LEFT JOIN decks d ON t.deck_id = d.id
            WHERE t.due_date >= NOW() OR t.due_date IS NULL
            ORDER BY t.due_date ASC NULLS LAST
            LIMIT 10
        ');
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function mapRowToTask(array $row): Task
    {
        return new Task(
            (int) $row['id'],
            (int) $row['class_id'],
            $row['deck_id'] ? (int) $row['deck_id'] : null,
            $row['title'],
            $row['description'] ?? null,
            $row['due_date'] ?? null,
            $row['created_at'] ?? null,
            $row['deck_title'] ?? null
        );
    }
}
