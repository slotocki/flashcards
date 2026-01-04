<?php

class Deck
{
    private int $id;
    private int $teacherId;
    private ?int $classId;  // Deprecated - używaj class_decks
    private string $title;
    private ?string $description;
    private string $level;
    private ?string $imageUrl;
    private bool $isPublic;
    private ?string $shareToken;
    private int $viewsCount;
    private ?string $createdAt;
    private ?int $cardCount;
    private ?float $averageRating;
    private ?int $ratingsCount;
    private ?string $className;
    private ?string $teacherName;

    public function __construct(
        int $id,
        int $teacherId,
        ?int $classId,
        string $title,
        ?string $description = null,
        string $level = 'beginner',
        ?string $imageUrl = null,
        bool $isPublic = false,
        ?string $shareToken = null,
        int $viewsCount = 0,
        ?string $createdAt = null,
        ?int $cardCount = null,
        ?float $averageRating = null,
        ?int $ratingsCount = null,
        ?string $className = null,
        ?string $teacherName = null
    ) {
        $this->id = $id;
        $this->teacherId = $teacherId;
        $this->classId = $classId;
        $this->title = $title;
        $this->description = $description;
        $this->level = $level;
        $this->imageUrl = $imageUrl;
        $this->isPublic = $isPublic;
        $this->shareToken = $shareToken;
        $this->viewsCount = $viewsCount;
        $this->createdAt = $createdAt;
        $this->cardCount = $cardCount;
        $this->averageRating = $averageRating;
        $this->ratingsCount = $ratingsCount;
        $this->className = $className;
        $this->teacherName = $teacherName;
    }

    public function getId(): int { return $this->id; }
    public function getTeacherId(): int { return $this->teacherId; }
    public function getClassId(): ?int { return $this->classId; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getLevel(): string { return $this->level; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function isPublic(): bool { return $this->isPublic; }
    public function getShareToken(): ?string { return $this->shareToken; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getCardCount(): ?int { return $this->cardCount; }
    public function getAverageRating(): ?float { return $this->averageRating; }
    public function getRatingsCount(): ?int { return $this->ratingsCount; }
    public function getClassName(): ?string { return $this->className; }
    public function getTeacherName(): ?string { return $this->teacherName; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'teacherId' => $this->teacherId,
            'classId' => $this->classId,
            'title' => $this->title,
            'description' => $this->description,
            'level' => $this->level,
            'imageUrl' => $this->imageUrl,
            'isPublic' => $this->isPublic,
            'shareToken' => $this->shareToken,
            'viewsCount' => $this->viewsCount,
            'createdAt' => $this->createdAt,
            'cardCount' => $this->cardCount,
            'averageRating' => $this->averageRating,
            'ratingsCount' => $this->ratingsCount,
            'className' => $this->className,
            'teacherName' => $this->teacherName
        ];
    }
}
