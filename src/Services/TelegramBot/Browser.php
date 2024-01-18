<?php

namespace Fearpro13\RemoteAuxControl\Services\TelegramBot;

use DateTimeImmutable;
use Fearpro13\RemoteAuxControl\App;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

class Browser
{
    /** @var OutputInterface $io */
    private static $io;

    public static function request($ch , string $method = 'GET'): ?Response
    {
        $responseBody = curl_exec($ch);
        $responseCode = curl_getinfo($ch , CURLINFO_HTTP_CODE);

        $diff = curl_getinfo($ch,CURLINFO_TOTAL_TIME);
        $diffFormatted = round($diff , 2);

        $now = new DateTimeImmutable();
        $nowFormatted = $now->format(App::APP_TIME_FORMAT);
        $url = curl_getinfo($ch , CURLINFO_EFFECTIVE_URL);

        $speed = curl_getinfo($ch , CURLINFO_SPEED_DOWNLOAD);
        $kbSpeed = round($speed / 1024,1);

        $size = curl_getinfo($ch , CURLINFO_SIZE_DOWNLOAD);
        $kbSize = round($size / 1024 , 1);

        if (!is_null(self::$io)) {
            $message = "[$nowFormatted] $method $url $responseCode [$diffFormatted s][$kbSize KB, $kbSpeed kB/sec]";
            self::$io->writeln($message);
        }

        if($responseCode === 0){
            return null;
        }

        return new Response($responseBody , $responseCode);
    }

    public static function setOutput(OutputInterface $output): void
    {
        self::$io = $output;
    }
}