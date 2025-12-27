<?php

class Progress
{
    private int $id;
    private int $userId;
    private int $cardId;
    private string $status;
    private int $correctStreak;
    private int $wrongStreak;
    private ?string $lastReviewed;
    private ?string $createdAt;

    public function __construct(
        int $id,
        int $userId,
        int $cardId,
        string $status = 'new',
        int $correctStreak = 0,
        int $wrongStreak = 0,
        ?string $lastReviewed = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->cardId = $cardId;
        $this->status = $status;
        $this->correctStreak = $correctStreak;
        $this->wrongStreak = $wrongStreak;
        $this->lastReviewed = $lastReviewed;
        $this->createdAt = $createdAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCardId(): int
    {
        return $this->cardId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCorrectStreak(): int
    {
        return $this->correctStreak;
    }

    public function getWrongStreak(): int
    {
        return $this->wrongStreak;
    }

    public function getLastReviewed(): ?string
    {
        return $this->lastReviewed;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'cardId' => $this->cardId,
            'status' => $this->status,
            'correctStreak' => $this->correctStreak,
            'wrongStreak' => $this->wrongStreak,
            'lastReviewed' => $this->lastReviewed,
            'createdAt' => $this->createdAt
        ];
    }
}
