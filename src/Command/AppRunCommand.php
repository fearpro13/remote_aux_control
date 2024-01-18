<?php

namespace Fearpro13\RemoteAuxControl\Command;

use Fearpro13\RemoteAuxControl\App;
use Fearpro13\RemoteAuxControl\Services\TelegramBot\Browser;
use Fearpro13\RemoteAuxControl\Services\TelegramBot\TelegramBot;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppRunCommand extends Command
{
    protected static $defaultName = "app:run";
    protected static $defaultDescription = "Start application";

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = file_get_contents(App::CONFIG_PATH);

        if (!is_string($config)) {
            throw new RuntimeException(App::APP_CONFIG_NULL);
        }

        $configParsed = json_decode($config, true);

        if (!is_array($configParsed)) {
            throw new RuntimeException(App::APP_CONFIG_BROKEN);
        }

        @['name' => $name, 'secret' => $secret, 'chat_id' => $chatId] = $configParsed;

        if (is_null($name)) {
            throw new RuntimeException(App::APP_CONFIG_MISSING_NAME);
        }

        if (is_null($secret)) {
            throw new RuntimeException(App::APP_CONFIG_MISSING_SECRET);
        }

        if (is_null($chatId)) {
            throw new RuntimeException(App::APP_CONFIG_MISSING_CHAT_ID);
        }

        $app = new App($name);

        TelegramBot::setSecret($secret);
        TelegramBot::setChatId($chatId);

        $app->setOutput($output);
        Browser::setOutput($output);

        return $app->run();
    }
}