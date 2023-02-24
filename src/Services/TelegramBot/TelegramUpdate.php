<?php

namespace Fearpro13\RemoteAuxControl\Services\TelegramBot;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

class TelegramUpdate
{
    /** @var string $firstName */
    private $firstName;

    /** @var string$lastName */
    private $lastName;

    /** @var string$login */
    private $login;

    /** @var DateTimeInterface $date */
    private $date;

    /** @var string $text */
    private $text;

    /** @var int $id */
    private $id;

    /** @var bool $isBot */
    private $isBot;

    /**
     * @param Response $response
     * @return TelegramUpdate[]
     */
    public static function parseAll(Response $response): array
    {
        $updates = [];

        $responseParsed = json_decode($response->getContent() , true);

        $result = $responseParsed['result'] ?? null;

        if (is_array($result)) {
            foreach ($result as $fullMessage) {
                $updates[] = self::parse($fullMessage);
            }
        }

        return $updates;
    }

    public static function parse(array $updateArray): self
    {
        $update = new self();

        $updateId = $updateArray['update_id'];
        $message = $updateArray['message'] ?? null;

        $text = $message['text'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;
        $lastName = $message['from']['last_name'] ?? null;
        $login = $message['from']['username'] ?? null;
        $isBot = $message['from']['is_bot'] ?? null;
        $dateTimestamp = $message['date'] ?? null;

        $now = new DateTimeImmutable();
        $date =
            $now
                ->setTimestamp($dateTimestamp);

        $update->firstName = $firstName;
        $update->lastName = $lastName;
        $update->login = $login;
        $update->date = $date;
        $update->text = $text;
        $update->id = $updateId;
        $update->isBot = $isBot;

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

    public function isBot():bool{
        return $this->isBot;
    }
}