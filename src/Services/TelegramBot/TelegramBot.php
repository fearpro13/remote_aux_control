<?php

namespace Fearpro13\RemoteAuxControl\Services\TelegramBot;

use Exception;
use RuntimeException;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TelegramBot extends NullOutput
{
    private static $secret;

    private static $chatId;

    private static $lastUpdateId;
    private static $updatesInitialized = false;
    private static $lastUpdated = 0;

    private const UPDATE_DELAY = 2;

    public static function info($message):array
    {
        return self::sendMessage($message);
    }

    public function writeln($messages, int $options = self::OUTPUT_NORMAL):void
    {
        self::info(is_array($messages) ? implode("\n", $messages) : $messages);
    }

    public static function setSecret(string $secret):void{
        self::$secret = $secret;
    }

    public static function setChatId(int $chatId):void{
        self::$chatId = $chatId;
    }


    /**
     * Отправка сообщения на Telegram API
     * @param string $message
     * @return array
     */
    private static function sendMessage(string $message):array
    {
        $chatId = self::$chatId;

        try {
            $method = "sendMessage";
            $uri = "https://api.telegram.org/bot" . self::$secret . "/$method";

            $ch = curl_init();

            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => true
            ];

            $options = [
                CURLOPT_URL => $uri,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_VERBOSE => false,
                CURLOPT_CONNECTTIMEOUT => 10
            ];

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $code = curl_getinfo($ch)['http_code'];

            return [
                'code' => $code,
                'response' => $response
            ];
        } catch (Exception $exception) {
            return [
                'code' => -1,
                'response' => 'Telegram bot failure'
            ];
        }
    }

    public static function sendDocument($documentPath ,string $caption): ?Response
    {
        $chatId = self::$chatId;

        try {
            $method = "sendDocument";
            $uri = "https://api.telegram.org/bot" . self::$secret . "/$method";

            $ch = curl_init();

            $data = [
                'chat_id' => $chatId ,
                'document' => $documentPath ,
                'caption' => $caption ,
                //'protect_content' => true ,
                //'disable_web_page_preview' => true
            ];

            $options = [
                CURLOPT_URL => $uri ,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: multipart/form-data'
                ] ,
                CURLOPT_POST => true ,
                CURLOPT_POSTFIELDS => $data ,
                CURLOPT_RETURNTRANSFER => true ,
                CURLOPT_VERBOSE => false,
                CURLOPT_CONNECTTIMEOUT => 30
            ];

            curl_setopt_array($ch , $options);

            return Browser::request($ch , 'POST');
        } catch (Throwable $throwable) {
            return new Response($throwable->getMessage() . $throwable->getFile() . $throwable->getLine() , 500);
        }
    }

    /**
     * @return TelegramUpdate[]
     */
    public static function askForUpdates(): array
    {
        $now = microtime(true);

        $diff = $now - self::$lastUpdated;
        if ($diff < self::UPDATE_DELAY) {
            $requiredSleep = (self::UPDATE_DELAY - $diff) * 1000000;
            usleep($requiredSleep);
        }

            $method = "getUpdates";
            $uri = "https://api.telegram.org/bot" . self::$secret . "/$method";

            $ch = curl_init();

            $data = [
                'offset' => is_null(self::$lastUpdateId) ? 0 : self::$lastUpdateId,
                'limit' => 100,
                'timeout' => 1,
//                'allowed_updates' => [
//                    "text"
//                ]
            ];

            $options = [
                CURLOPT_URL => $uri,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_VERBOSE => false,
                CURLOPT_CONNECTTIMEOUT => 30
            ];

            curl_setopt_array($ch, $options);

            $response = Browser::request($ch);

            if (is_null($response)) {
                $errorCode = curl_errno($ch);
                $errorMessage = curl_strerror($errorCode);

                $message = "Curl, code:$errorCode, description: $errorMessage";

                throw new RuntimeException($message);
            }

            $updates = TelegramUpdate::parseAll($response);

            if (!is_null(self::$lastUpdateId)) {
                $updates = array_filter($updates, static function (TelegramUpdate $update) {
                    return $update->getId() > self::$lastUpdateId;
                });
            }

            if (!empty($updates)) {
                $lastUpdate = end($updates);

                self::$lastUpdateId = $lastUpdate->getId();
            }

            if (!self::$updatesInitialized) {
                self::$updatesInitialized = true;
                $updates = [];
            }

        self::$lastUpdated = microtime(true);

        return $updates;
    }
}