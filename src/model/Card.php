<?php

class Card
{
    private int $id;
    private int $deckId;
    private string $front;
    private string $back;
    private ?string $imagePath;
    private ?string $createdAt;

    public function __construct(
        int $id,
        int $deckId,
        string $front,
        string $back,
        ?string $imagePath = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->deckId = $deckId;
        $this->front = $front;
        $this->back = $back;
        $this->imagePath = $imagePath;
        $this->createdAt = $createdAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDeckId(): int
    {
        return $this->deckId;
    }

    public function getFront(): string
    {
        return $this->front;
    }

    public function getBack(): string
    {
        return $this->back;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'deckId' => $this->deckId,
            'front' => $this->front,
            'back' => $this->back,
            'imagePath' => $this->imagePath,
            'createdAt' => $this->createdAt
        ];
    }
}
