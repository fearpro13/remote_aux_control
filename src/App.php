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
    public const CONFIG_PATH = ROOT_DIR . "/../config.json";
    public const TEMP_PATH = ROOT_DIR . "/../command_result.html";

    public const APP_TIME_FORMAT = "Y-m-d H:i:s";

    public const APP_CANT_PREPARE_CONFIG = "Could not prepare config file";
    public const APP_CANT_SAVE_CONFIG = "Could not save config file";

    public const APP_CONFIG_NULL = "App config is missing or could not be read(Did you run app:init before?)";
    public const APP_CONFIG_BROKEN = "App config has incorrect format. Consider manual delete of config file and then run app:init";
    public const APP_CONFIG_MISSING_NAME = "App config is missing 'name' value";
    public const APP_CONFIG_MISSING_CHAT_ID = "App config is missing 'chat_id' value";
    public const APP_CONFIG_MISSING_SECRET = "App config is missing 'secret' value";

    public const APP_STOPPED_BY_TG = "App stopped by telegram command";
    public const APP_PAUSED_BY_TG = "App stopped by telegram command";
    public const APP_RELOADED = "App reloaded"; //wtf

    private $name;

    private $isRunning = true;

    private $isPaused = false;

    private $timeout = 5;

    /** @var OutputInterface $io */
    private $io;

    private $interceptionAction;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->io = $output;
    }

    public function run(): int
    {
        while ($this->isRunning) {
            try {
                $this->handleUpdates();
            } catch (Throwable $throwable) {
                $this->error($throwable);
                sleep(5);
            }

            if (!$this->isPaused) {
                try {
                    $this->main();
                } catch (Throwable $throwable) {
                    $this->error($throwable);
                    if (!($throwable instanceof RuntimeException) && !$this->isPaused) {
                        $this->error($throwable);
                        $this->isPaused = true;
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    private function main(): void
    {
        self::interruptibleSleep($this->timeout, function () {
            $this->handleUpdates();
        });
    }

    private function handleUpdates(): void
    {
        try {
            $updates = TelegramBot::askForUpdates();
        } catch (Throwable $throwable) {
            $this->error($throwable);

            return;
        }

        $keywords = [
            'stop' => function () {
                $this->stop();
            },
            'next' => function () {
                $this->resume();
            },
            'pause' => function () {
                $this->pause();
            },
            'speed' => function ($message) {
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
            'commands' => function ($message, $keywords) {
                $preparedMessage = "";
                foreach ($keywords as $keyword => $callback) {
                    $preparedMessage .= $keyword . "\n";
                }
                $this->log($preparedMessage);
            },
            'run' => function ($message) {
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
            'reboot' => function () {
                $rebootCommand = "shutdown -r";
                $this->runCommand($rebootCommand);
            },
            'output' => function ($message) {
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
            if ($update->isBot()) {
                continue;
            }

            $message = mb_strtolower(trim($update->getText()));

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
            $this->stopProcess(self::APP_STOPPED_BY_TG);
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
            $this->stopProcess(self::APP_PAUSED_BY_TG);
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
            $this->stopProcess(self::APP_RELOADED);
        };

        $action = $this->interceptionAction;
        if (!is_null($action)) {
            $this->interceptionAction = null;
            $action();
        }
    }

    private static function interruptibleSleep(int $seconds, callable $interruptor): void
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

        $commandOutput = str_replace("\n", "<br>", $commandOutput);
        $bytesWritten = file_put_contents(self::TEMP_PATH, $commandOutput);

        if (!$bytesWritten) {
            return Command::FAILURE;
        }

        $document = new CURLFile(self::TEMP_PATH);

        TelegramBot::sendDocument($document, "{$this->getNodeName()}: $commandPath");

        return $returnCode;
    }

    public function runDetachedCommand(string $commandPath): void
    {
        $this->log("Running detached: $commandPath");

        $commandPath = "nohup $commandPath &";

        exec($commandPath);
    }

    public function getNodeName(): string
    {
        return $this->name;
    }

    public function log(string $message): void
    {
        $now = new DateTimeImmutable();
        $nodeName = $this->getNodeName();

        $nowFormatted = $now->format(self::APP_TIME_FORMAT);

        $messageFormatted = "$nodeName\n$nowFormatted\n\n$message";

        $this->out($messageFormatted);
    }

    private function error(string $message): void
    {
        $now = new DateTimeImmutable();
        $name = $this->getNodeName();

        $nowFormatted = $now->format(self::APP_TIME_FORMAT);

        $messageFormatted = "$name\n$nowFormatted\n\nError:\n$message";

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
            $dateFormatted = is_null($date) ? "UNKNOWN DATE" : $date->format(self::APP_TIME_FORMAT);

            $chatId = $update->getChatId();
            $chatTitle = $update->getChatTitle();

            $messageFormatted = "[$dateFormatted][$chatId,$chatTitle][$lastName, $firstName, @$login]: $text";
            $this->out($messageFormatted);
        }
    }
}