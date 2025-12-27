<?php

class Task
{
    private int $id;
    private int $classId;
    private ?int $deckId;
    private string $title;
    private ?string $description;
    private ?string $dueDate;
    private ?string $createdAt;
    private ?string $deckTitle;

    public function __construct(
        int $id,
        int $classId,
        ?int $deckId,
        string $title,
        ?string $description = null,
        ?string $dueDate = null,
        ?string $createdAt = null,
        ?string $deckTitle = null
    ) {
        $this->id = $id;
        $this->classId = $classId;
        $this->deckId = $deckId;
        $this->title = $title;
        $this->description = $description;
        $this->dueDate = $dueDate;
        $this->createdAt = $createdAt;
        $this->deckTitle = $deckTitle;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getClassId(): int
    {
        return $this->classId;
    }

    public function getDeckId(): ?int
    {
        return $this->deckId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDueDate(): ?string
    {
        return $this->dueDate;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getDeckTitle(): ?string
    {
        return $this->deckTitle;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'classId' => $this->classId,
            'deckId' => $this->deckId,
            'title' => $this->title,
            'description' => $this->description,
            'dueDate' => $this->dueDate,
            'createdAt' => $this->createdAt,
            'deckTitle' => $this->deckTitle
        ];
    }
}
