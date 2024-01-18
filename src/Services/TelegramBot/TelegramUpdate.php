<?php

namespace Fearpro13\RemoteAuxControl\Services\TelegramBot;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

class TelegramUpdate
{
    /** @var string $firstName */
    private $firstName;

    /** @var string $lastName */
    private $lastName;

    /** @var string $login */
    private $login;

    /** @var DateTimeInterface $date */
    private $date;

    /** @var string $text */
    private $text;

    /** @var int $id */
    private $id;

    /** @var bool $isBot */
    private $isBot;

    /** @var int $chatId */
    private $chatId;

    /** @var string $chatTitle */
    private $chatTitle;

    /**
     * @param Response $response
     * @return TelegramUpdate[]
     */
    public static function parseResponseIntoUpdates(Response $response): array
    {
        $responseParsed = json_decode($response->getContent() ?: "", true);

        if (!is_array($responseParsed)) {
            return [];
        }

        $result = $responseParsed['result'] ?? null;

        if (!is_array($result)) {
            return [];
        }

        $updates = [];

        foreach ($result as $fullMessage) {
            $update = self::parseTelegramUpdate($fullMessage);

            if (!is_null($update)) {
                $updates[] = $update;
            }
        }

        return $updates;
    }

    public static function parseTelegramUpdate(array $updateArray): ?self
    {
        $update = new self();

        @[
            'update_id' => $update->id,
            'message' => $message
        ] = $updateArray;

        if(!is_array($message)){
            return null;
        }

        @[
            'text' => $update->text,
            'from' => [
                'first_name' => $update->firstName,
                'last_name' => $update->lastName,
                'username' => $update->login,
                'is_bot' => $update->isBot
            ],
            'chat' => [
              'id' => $update->chatId,
              'title' => $update->chatTitle
            ],
            'date' => $dateTimestamp
        ] = $message;

        $update->date = (new DateTimeImmutable())->setTimestamp($dateTimestamp);

        return $update;
    }

    /**
     * @return string
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @return string
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @return string
     */
    public function getLogin(): ?string
    {
        return $this->login;
    }

    /**
     * @return DateTimeInterface
     */
    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function isBot(): bool
    {
        return $this->isBot;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }

    public function getChatTitle(): string
    {
        return $this->chatTitle;
    }
}