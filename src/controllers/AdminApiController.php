<?php

require_once 'ApiController.php';
require_once __DIR__.'/../repository/UserRepository.php';
require_once __DIR__.'/../repository/ClassRepository.php';

class AdminApiController extends ApiController
{
    private $userRepository;
    private $classRepository;
    
    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->classRepository = new ClassRepository();
    }
    
    /**
     * Pobiera listę wszystkich użytkowników
     * GET /api/admin/users
     */
    public function users()
    {
        $this->requireAuth();
        $this->requireRole('admin');
        
        try {
            $users = $this->userRepository->getAllUsers();
            
            $result = array_map(function($user) {
                return [
                    'id' => $user->getId(),
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole(),
                    'enabled' => $user->isEnabled(),
                    'bio' => $user->getBio()
                ];
            }, $users);
            
            $this->success($result);
        } catch (Exception $e) {
            $this->error('DATABASE_ERROR', 'Błąd pobierania użytkowników', 500);
        }
    }
    
    /**
     * Zmienia rolę użytkownika
     * PUT /api/admin/users/{id}/role
     */
    public function updateRole(int $userId)
    {
        $this->requireAuth();
        $this->requireRole('admin');
        
        $input = $this->getJsonInput();
        
        if (empty($input['role'])) {
            $this->error('VALIDATION_ERROR', 'Rola jest wymagana', 400);
        }
        
        $validRoles = ['student', 'teacher', 'admin'];
        if (!in_array($input['role'], $validRoles)) {
            $this->error('VALIDATION_ERROR', 'Nieprawidłowa rola', 400);
        }
        
        // Nie pozwól adminowi zmienić własnej roli
        if ($userId === $_SESSION['user_id']) {
            $this->error('FORBIDDEN', 'Nie możesz zmienić własnej roli', 403);
        }
        
        try {
            $this->userRepository->updateUserRole($userId, $input['role']);
            $this->success(['message' => 'Rola została zmieniona']);
        } catch (Exception $e) {
            $this->error('DATABASE_ERROR', 'Błąd zmiany roli', 500);
        }
    }
    
    /**
     * Zmienia status użytkownika (enabled/disabled)
     * PUT /api/admin/users/{id}/status
     */
    public function updateStatus(int $userId)
    {
        $this->requireAuth();
        $this->requireRole('admin');
        
        $input = $this->getJsonInput();
        
        if (!isset($input['enabled'])) {
            $this->error('VALIDATION_ERROR', 'Status jest wymagany', 400);
        }
        
        // Nie pozwól adminowi zablokować siebie
        if ($userId === $_SESSION['user_id']) {
            $this->error('FORBIDDEN', 'Nie możesz zablokować własnego konta', 403);
        }
        
        try {
            $this->userRepository->setUserEnabled($userId, (bool)$input['enabled']);
            $this->success(['message' => 'Status został zmieniony']);
        } catch (Exception $e) {
            $this->error('DATABASE_ERROR', 'Błąd zmiany statusu', 500);
        }
    }
    
    /**
     * Pobiera listę wszystkich klas
     * GET /api/admin/classes
     */
    public function allClasses()
    {
        $this->requireAuth();
        $this->requireRole('admin');
        
        try {
            $classes = $this->classRepository->getAllClasses();
            
            $result = array_map(function($class) {
                return [
                    'id' => $class->getId(),
                    'name' => $class->getName(),
                    'description' => $class->getDescription(),
                    'teacherId' => $class->getTeacherId(),
                    'teacherName' => $class->getTeacherName(),
                    'joinCode' => $class->getJoinCode(),
                    'language' => $class->getLanguage(),
                    'createdAt' => $class->getCreatedAt()
                ];
            }, $classes);
            
            $this->success($result);
        } catch (Exception $e) {
            $this->error('DATABASE_ERROR', 'Błąd pobierania klas', 500);
        }
    }
}