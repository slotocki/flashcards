<?php

require_once 'ApiController.php';
require_once __DIR__ . '/../repository/ClassRepository.php';
require_once __DIR__ . '/../repository/TaskRepository.php';

/**
 * Kontroler API dla operacji na klasach
 */
class ClassApiController extends ApiController
{
    private ClassRepository $classRepository;
    private TaskRepository $taskRepository;

    public function __construct()
    {
        parent::__construct();
        $this->classRepository = ClassRepository::getInstance();
        $this->taskRepository = TaskRepository::getInstance();
    }

    /**
     * GET /api/classes - lista klas
     * POST /api/classes - tworzenie nowej klasy (teacher only)
     */
    public function index(): void
    {
        $this->requireAuth();
        
        if ($this->isPost()) {
            $this->createClass();
            return;
        }
        
        $this->requireMethod('GET');
        
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role === 'teacher') {
            $classes = $this->classRepository->getClassesByTeacherId($userId);
        } elseif ($role === 'student') {
            $classes = $this->classRepository->getClassesByStudentId($userId);
        } elseif ($role === 'admin') {
            // Admin widzi wszystkie klasy - można rozszerzyć
            $classes = $this->classRepository->getClassesByTeacherId($userId);
        } else {
            $classes = [];
        }
        
        $this->success($classes);
    }

    /**
     * Tworzenie nowej klasy (tylko teacher)
     */
    private function createClass(): void
    {
        $this->requireRole('teacher', 'admin');
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '') ?: null;
        $language = trim($input['language'] ?? '') ?: null;
        
        if (empty($name)) {
            $this->error('MISSING_NAME', 'Nazwa klasy jest wymagana', 400);
        }
        
        if (mb_strlen($name) > 200) {
            $this->error('NAME_TOO_LONG', 'Nazwa klasy może mieć maksymalnie 200 znaków', 400);
        }
        
        try {
            $classId = $this->classRepository->createClass(
                $this->getUserId(),
                $name,
                $description,
                $language
            );
            
            $class = $this->classRepository->getClassById($classId);
            
            $this->success($class->toArray(), 201);
        } catch (Exception $e) {
            error_log("Error creating class: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas tworzenia klasy', 500);
        }
    }

    /**
     * GET /api/classes/{id} - szczegóły klasy
     * DELETE /api/classes/{id} - usuwanie klasy
     */
    public function show(int $classId): void
    {
        $this->requireAuth();
        
        // Obsłuż DELETE
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $this->delete($classId);
            return;
        }
        
        $this->requireMethod('GET');
        
        $class = $this->classRepository->getClassById($classId);
        
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        // Sprawdź dostęp
        if (!$this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole())) {
            $this->error('FORBIDDEN', 'Brak dostępu do tej klasy', 403);
        }
        
        $this->success($class->toArray());
    }

    /**
     * POST /api/classes/join - dołączanie do klasy kodem
     */
    public function join(): void
    {
        $this->requireMethod('POST');
        $this->requireRole('student');
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $joinCode = strtoupper(trim($input['joinCode'] ?? ''));
        
        if (empty($joinCode)) {
            $this->error('MISSING_CODE', 'Kod dołączenia jest wymagany', 400);
        }
        
        if (strlen($joinCode) !== 8) {
            $this->error('INVALID_CODE', 'Kod dołączenia musi mieć 8 znaków', 400);
        }
        
        $class = $this->classRepository->getClassByJoinCode($joinCode);
        
        if (!$class) {
            $this->error('CLASS_NOT_FOUND', 'Nie znaleziono klasy o podanym kodzie', 404);
        }
        
        $studentId = $this->getUserId();
        
        // Sprawdź czy student już jest w klasie
        if ($this->classRepository->isStudentInClass($class->getId(), $studentId)) {
            $this->error('ALREADY_MEMBER', 'Już należysz do tej klasy', 409);
        }
        
        // Dodaj studenta do klasy
        $success = $this->classRepository->addStudentToClass($class->getId(), $studentId);
        
        if (!$success) {
            $this->error('JOIN_FAILED', 'Nie udało się dołączyć do klasy', 500);
        }
        
        $this->success([
            'message' => 'Dołączono do klasy',
            'class' => $class->toArray()
        ]);
    }

    /**
     * GET /api/classes/{id}/members - członkowie klasy
     */
    public function members(int $classId): void
    {
        $this->requireMethod('GET');
        $this->requireAuth();
        
        $class = $this->classRepository->getClassById($classId);
        
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        // Sprawdź dostęp (tylko nauczyciel danej klasy lub admin)
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do przeglądania członków', 403);
        }
        
        $members = $this->classRepository->getClassMembers($classId);
        
        $this->success($members);
    }

    /**
     * GET /api/classes/{id}/tasks - zadania dla klasy
     * POST /api/classes/{id}/tasks - tworzenie zadania (teacher only)
     */
    public function tasks(int $classId): void
    {
        $this->requireAuth();
        
        $class = $this->classRepository->getClassById($classId);
        
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        // Sprawdź dostęp
        if (!$this->classRepository->hasAccessToClass($classId, $this->getUserId(), $this->getUserRole())) {
            $this->error('FORBIDDEN', 'Brak dostępu do tej klasy', 403);
        }
        
        if ($this->isPost()) {
            $this->createTask($classId, $class);
            return;
        }
        
        $this->requireMethod('GET');
        
        $tasks = $this->taskRepository->getTasksByClassId($classId);
        
        $this->success($tasks);
    }

    /**
     * Tworzenie zadania
     */
    private function createTask(int $classId, $class): void
    {
        // Tylko nauczyciel klasy może tworzyć zadania
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && !($role === 'teacher' && $class->getTeacherId() === $userId)) {
            $this->error('FORBIDDEN', 'Brak uprawnień do tworzenia zadań', 403);
        }
        
        $input = $this->getJsonInput();
        if (!$input) {
            $this->error('INVALID_JSON', 'Nieprawidłowy format JSON', 400);
        }
        
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '') ?: null;
        $deckId = isset($input['deckId']) ? (int) $input['deckId'] : null;
        $dueDate = trim($input['dueDate'] ?? '') ?: null;
        
        if (empty($title)) {
            $this->error('MISSING_TITLE', 'Tytuł zadania jest wymagany', 400);
        }
        
        try {
            $taskId = $this->taskRepository->createTask($classId, $title, $description, $deckId, $dueDate);
            $task = $this->taskRepository->getTaskById($taskId);
            
            $this->success($task->toArray(), 201);
        } catch (Exception $e) {
            error_log("Error creating task: " . $e->getMessage());
            $this->error('SERVER_ERROR', 'Błąd podczas tworzenia zadania', 500);
        }
    }
    
    /**
     * DELETE /api/classes/{id} - usuwanie klasy
     */
    public function delete(int $classId): void
    {
        $this->requireMethod('DELETE');
        $this->requireAuth();
        
        $class = $this->classRepository->getClassById($classId);
        
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        // Sprawdź czy użytkownik jest właścicielem lub adminem
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && $class->getTeacherId() !== $userId) {
            $this->error('FORBIDDEN', 'Brak uprawnień do usunięcia klasy', 403);
        }
        
        try {
            $this->classRepository->deleteClass($classId);
            $this->success(['message' => 'Klasa została usunięta']);
        } catch (Exception $e) {
            $this->error('SERVER_ERROR', 'Błąd usuwania klasy', 500);
        }
    }
    
    /**
     * DELETE /api/classes/{classId}/members/{studentId} - usuwanie ucznia z klasy
     */
    public function removeMember(int $classId, int $studentId): void
    {
        $this->requireMethod('DELETE');
        $this->requireAuth();
        
        $class = $this->classRepository->getClassById($classId);
        
        if (!$class) {
            $this->error('NOT_FOUND', 'Klasa nie istnieje', 404);
        }
        
        // Sprawdź czy użytkownik jest właścicielem lub adminem
        $userId = $this->getUserId();
        $role = $this->getUserRole();
        
        if ($role !== 'admin' && $class->getTeacherId() !== $userId) {
            $this->error('FORBIDDEN', 'Brak uprawnień do usuwania uczniów', 403);
        }
        
        try {
            $this->classRepository->removeStudentFromClass($classId, $studentId);
            $this->success(['message' => 'Uczeń został usunięty z klasy']);
        } catch (Exception $e) {
            $this->error('SERVER_ERROR', 'Błąd usuwania ucznia', 500);
        }
    }
}
