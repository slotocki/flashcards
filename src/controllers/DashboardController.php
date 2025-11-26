<?php

require_once 'AppController.php';
require_once __DIR__.'/../model/Flashcard.php';
require_once __DIR__.'/../repository/Repository.php';
require_once __DIR__.'/../repository/UserRepository.php';

class DashboardController extends AppController
{
    private $userRepository;
    
    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $users = $this->userRepository->getUsers();
        var_dump($users);
    }

    public function dashboard()
    {
        $this->render('dashboard');
    }

    public function addFlashcard()
    {
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            return $this->json(['error' => 'Unauthorized']);
        }

        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

        if ($contentType === "application/json") {
            $content = trim(file_get_contents("php://input"));
            $decoded = json_decode($content, true);

            $question = $decoded['question'] ?? '';
            $answer = $decoded['answer'] ?? '';
            $category = $decoded['category'] ?? '';

            if (empty($question) || empty($answer)) {
                http_response_code(400);
                return $this->json(['error' => 'Question and answer are required']);
            }

            $flashcard = new Flashcard(
                null,
                $_SESSION['user_id'],
                $question,
                $answer,
                $category
            );

            // TODO: Dodaj FlashcardRepository lub zapisz do Repository
            return $this->json(['success' => true]);
        }
    }

    private function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}