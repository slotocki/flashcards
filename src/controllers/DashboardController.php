<?php

require_once 'AppController.php';
require_once __DIR__.'/../repository/Repository.php';
require_once __DIR__.'/../repository/UserRepository.php';

class DashboardController extends AppController
{
    private $userRepository;
    
    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }
    
    /**
     * Sprawdza czy użytkownik jest zalogowany
     */
    private function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Zwraca rolę zalogowanego użytkownika
     */
    private function getUserRole(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Wymusza zalogowanie - przekierowuje do /login jeśli nie zalogowany
     */
    private function requireAuth(): bool
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login');
            exit();
        }
        return true;
    }
    
    /**
     * Strona główna - przekierowanie w zależności od stanu logowania i roli
     */
    public function index()
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login');
            exit();
        }
        
        $role = $this->getUserRole();
        
        // Przekieruj w zależności od roli
        switch ($role) {
            case 'admin':
                $this->render('admin_panel');
                break;
            case 'teacher':
                $this->render('teacher_dashboard');
                break;
            default:
                $this->render('dashboard');
        }
    }

    public function dashboard()
    {
        $this->requireAuth();
        $this->render('dashboard');
    }
    
    public function study()
    {
        $this->requireAuth();
        $this->render('study');
    }
    
    public function progress()
    {
        $this->requireAuth();
        $this->render('progress');
    }
    
    public function teacher()
    {
        $this->requireAuth();
        $this->render('teacher_panel');
    }
    
    public function joinClass()
    {
        $this->requireAuth();
        $this->render('join_class');
    }
    
    public function account()
    {
        $this->requireAuth();
        $this->render('account');
    }
    
    public function classView()
    {
        $this->requireAuth();
        $this->render('class_view');
    }
    
    public function admin()
    {
        $this->requireAuth();
        
        // Tylko admin ma dostęp
        if ($this->getUserRole() !== 'admin') {
            header('Location: /dashboard');
            exit();
        }
        
        $this->render('admin_panel');
    }
    
    public function community()
    {
        $this->requireAuth();
        $this->render('community');
    }
    
    /**
     * Publiczna strona zestawu fiszek - dostępna bez logowania
     */
    public function publicDeck()
    {
        // Ta strona jest publiczna - nie wymaga logowania
        $this->render('public_deck');
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}