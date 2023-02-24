<?php

namespace Fearpro13\RemoteAuxControl;

use CURLFile;
use DateTimeImmutable;
use Fearpro13\RemoteAuxControl\Services\TelegramBot\TelegramBot;
use Fearpro13\RemoteAuxControl\Services\TelegramBot\TelegramUpdate;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class App
{
    private $remoteNodeName;

    private $isRunning = true;

    private $isPaused = false;

    private $isBooted = false;

    private $timeout = 5;

    /** @var OutputInterface $io */
    private $io;

    private $interceptionAction;

    public function setOutput(OutputInterface $output): void
    {
        $this->io = $output;
    }

    public function boot(): void
    {
        $rootDir = ROOT_DIR;
        $configFile = "$rootDir/../config.json";

        $config = file_get_contents($configFile);

        if ($config === false) {
            throw new RuntimeException("Необходима инициализация");
        }

        $configParsed = json_decode($config, true);

        if (!is_array($configParsed)) {
            throw new RuntimeException("Некорректный файл конфигурации");
        }

        $remoteNodeName = $configParsed['node_name'] ?? null;
        $secret = $configParsed['secret'] ?? null;
        $chatId = $configParsed['chat_id'] ?? null;

        if (is_null($remoteNodeName)) {
            throw new RuntimeException("В конфигурации отсутствует имя удалённого узла");
        }

        if (is_null($secret)) {
            throw new RuntimeException("В конфигурации отсутствует токен доступа для телеграм API");
        }

        if (is_null($chatId)) {
            throw new RuntimeException("В конфигурации отсутствует ID чата");
        }

        $this->remoteNodeName = $remoteNodeName;

        TelegramBot::setSecret($secret);
        TelegramBot::setChatId($chatId);

        $this->isBooted = true;

        $this->log("Booted and running well");
    }

    public function run(): int
    {
        if (!$this->isBooted) {
            $this->error("Запуск без инициализации");
            return Command::FAILURE;
        }

        while ($this->isRunning) {
            try {
                $this->handleUpdates();
            } catch (Throwable $throwable) {
                $this->error($throwable->getMessage());
                sleep(5);
            }

            if (!$this->isPaused) {
                try {
                    $this->main();
                } catch (Throwable $throwable) {
                    $this->error($throwable->getMessage());
                    if (!($throwable instanceof RuntimeException) && !$this->isPaused) {
                        $this->error($throwable->getMessage());
                        $this->isPaused = true;
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    private function main(): void
    {
        $this->interruptibleSleep($this->timeout, function () {
            $this->handleUpdates();
        });
    }

    private function handleUpdates(): void
    {
        $updates = [];
        try {
            $updates = TelegramBot::askForUpdates();
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());
        }

        $keywords = [
//            'стоп' => function () {
//                $this->stop();
//            },
            'далее' => function () {
                $this->resume();
            },
            'пауза' => function () {
                $this->pause();
            },
            'скорость' => function ($message) {
                $words = explode(" ", $message);
                if (count($words) === 2) {
                    array_shift($words);
                    $timeout = current($words);
                    $timeout = is_numeric($timeout) ? max(1, (int)$timeout) : null;
                    if (!is_null($timeout)) {
                        $this->timeout = $timeout;
                        $this->reload();
                    }
                }
            },
            'команды' => function ($message, $keywords) {
                $preparedMessage = "";
                foreach ($keywords as $keyword => $callback) {
                    $preparedMessage .= $keyword . "\n";
                }
                $this->log($preparedMessage);
            },
            'запуск' => function ($message) {
                $words = explode(" ", $message);
                if (count($words) > 1) {
                    array_shift($words);
                    $commandPath = implode(" ", $words);

                    if (trim($commandPath) === "") {
                        return;
                    }

                    $this->runDetachedCommand($commandPath);
                }
            },
            'ребут' => function () {
                $rebootCommand = "shutdown -r";
                $this->runCommand($rebootCommand);
            },
            'вывод' => function ($message) {
                $words = explode(" ", $message);
                if (count($words) > 1) {
                    array_shift($words);
                    $commandPath = implode(" ", $words);

                    if (trim($commandPath) === "") {
                        return;
                    }

                    $this->runCommand($commandPath);
                }
            }
        ];

        $this->printTelegramMessages($updates);

        foreach ($updates as $update) {
            $message = mb_strtolower(trim($update->getText()));

            if ($update->isBot()) {
                continue;
            }

            foreach ($keywords as $keyword => $callback) {
                if (mb_stripos($message, $keyword) !== false) {
                    $callback($message, $keywords);
                    break;
                }
            }
        }
    }

    private function stop(): void
    {
        $this->isRunning = false;

        $this->interceptionAction = function () {
            $this->stopProcess("STOPPED");
        };

        $action = $this->interceptionAction;
        if (!is_null($action)) {
            $this->interceptionAction = null;
            $action();
        }
    }

    private function pause(): void
    {
        $this->isPaused = true;

        $this->interceptionAction = function () {
            $this->stopProcess("PAUSED");
        };

        $action = $this->interceptionAction;
        if (!is_null($action)) {
            $this->interceptionAction = null;
            $action();
        }
    }

    private function resume(): void
    {
        $this->isPaused = false;
    }

    private function reload(): void
    {
        $this->interceptionAction = function () {
            $this->stopProcess("RELOADED");
        };

        $action = $this->interceptionAction;
        if (!is_null($action)) {
            $this->interceptionAction = null;
            $action();
        }
    }

    private function interruptibleSleep(int $seconds, callable $interruptor): void
    {
        $step = 2;

        for ($i = 0; $i < $seconds; $i += $step) {
            sleep($step);
            $interruptor();
        }
    }

    private function stopProcess($message): void
    {
        throw new RuntimeException($message);
    }

    public function runCommand(string $commandPath): int
    {
        $this->log("Running: $commandPath");

        $d = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w')
        );

        $pd = proc_open($commandPath, $d, $pipes, null);

        $commandOutput = is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : "";

        $returnCode = proc_close($pd);

        $rootDir = ROOT_DIR;

        $tempFile = "$rootDir/command_result.html";

        unlink($tempFile);
        $commandOutput = str_replace("\n", "<br>", $commandOutput);
        file_put_contents($tempFile, $commandOutput, FILE_APPEND);

        $document = new CURLFile($tempFile);
        TelegramBot::sendDocument($document, $commandPath);

        unlink($tempFile);

        return $returnCode;
    }

    public function runDetachedCommand(string $commandPath): void
    {
        $returnCode = null;
        $output = [];

        $this->log("Running detached: $commandPath");

        $commandPath = "nohup $commandPath &";

        exec($commandPath, $output, $returnCode);
    }

    public function getNodeName(): string
    {
        return $this->remoteNodeName;
    }

    public function log(string $message): void
    {
        $now = new DateTimeImmutable();
        $nodeName = $this->getNodeName();

        $nowFormatted = $now->format("Y-m-d H:i:s");

        $messageFormatted = "$nodeName\n$nowFormatted\n\n$message";

        $this->out($messageFormatted);
    }

    private function error(string $message): void
    {
        $now = new DateTimeImmutable();
        $nodeName = $this->isBooted ? $this->getNodeName() : "UNKNOWN NODE";

        $nowFormatted = $now->format("Y-m-d H:i:s");

        $messageFormatted = "$nodeName\n$nowFormatted\n\nОшибка\n$message";

        $this->out($messageFormatted);
    }

    private function out(string $message): void
    {
        if (!is_null($this->io)) {
            $this->io->writeln($message);
        }
    }

    /**
     * @param TelegramUpdate[]|TelegramUpdate $updates
     */
    private function printTelegramMessages($updates): void
    {
        if (!is_array($updates)) {
            $updates = [$updates];
        }

        foreach ($updates as $update) {
            if (!($update instanceof TelegramUpdate)) {
                continue;
            }

            if ($update->isBot()) {
                continue;
            }

            //$updateId = $update->getId();
            $text = $update->getText();
            $firstName = $update->getFirstName();
            $lastName = $update->getLastName();
            $login = $update->getLogin();

            $date = $update->getDate();
            $dateFormatted = is_null($date) ? "UNKNOWN DATE" : $date->format("Y-m-d H:i:s");

            $messageFormatted = "[$dateFormatted] [$lastName, $firstName, @$login]: $text";
            $this->out($messageFormatted);
        }
    }
}