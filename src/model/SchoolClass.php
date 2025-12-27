<?php

class SchoolClass
{
    private int $id;
    private int $teacherId;
    private string $name;
    private ?string $description;
    private string $joinCode;
    private ?string $language;
    private ?string $createdAt;
    private ?string $teacherName;

    public function __construct(
        int $id,
        int $teacherId,
        string $name,
        ?string $description,
        string $joinCode,
        ?string $language = null,
        ?string $createdAt = null,
        ?string $teacherName = null
    ) {
        $this->id = $id;
        $this->teacherId = $teacherId;
        $this->name = $name;
        $this->description = $description;
        $this->joinCode = $joinCode;
        $this->language = $language;
        $this->createdAt = $createdAt;
        $this->teacherName = $teacherName;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTeacherId(): int
    {
        return $this->teacherId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getJoinCode(): string
    {
        return $this->joinCode;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getTeacherName(): ?string
    {
        return $this->teacherName;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'teacherId' => $this->teacherId,
            'name' => $this->name,
            'description' => $this->description,
            'joinCode' => $this->joinCode,
            'language' => $this->language,
            'createdAt' => $this->createdAt,
            'teacherName' => $this->teacherName
        ];
    }
}
