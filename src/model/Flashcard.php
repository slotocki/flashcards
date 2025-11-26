<?php

class Flashcard
{
    private $id;
    private $userId;
    private $question;
    private $answer;
    private $category;

    public function __construct($id, $userId, $question, $answer, $category = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->question = $question;
        $this->answer = $answer;
        $this->category = $category;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getQuestion()
    {
        return $this->question;
    }

    public function getAnswer()
    {
        return $this->answer;
    }

    public function getCategory()
    {
        return $this->category;
    }
}