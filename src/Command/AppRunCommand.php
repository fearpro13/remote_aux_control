<?php

namespace Fearpro13\RemoteAuxControl\Command;

use Fearpro13\RemoteAuxControl\App;
use Fearpro13\RemoteAuxControl\Services\TelegramBot\TelegramBot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppRunCommand extends Command
{
    protected static $defaultName = "app:run";
    protected static $defaultDescription = "Start application";

    private $app;

    public function __construct(string $name = null)
    {
        parent::__construct($name);

        $this->app = new App();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = $this->app;

        $telegramOutput = new TelegramBot();

        $app->setOutput($telegramOutput);

        $app->boot();

        return $app->run();
    }
}