<?php

class User
{
    private $id;
    private $email;
    private $password;
    private $firstName;
    private $lastName;
    private $role;
    private $enabled;
    private $bio;

    public function __construct(
        $id, 
        $email, 
        $password, 
        $firstName, 
        $lastName, 
        $role = 'student', 
        $enabled = true,
        $bio = null
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->role = $role;
        $this->enabled = $enabled;
        $this->bio = $bio;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function getRole(): string
    {
        return $this->role ?? 'student';
    }

    public function isEnabled(): bool
    {
        return $this->enabled ?? true;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
}