<?php

namespace Fearpro13\RemoteAuxControl\Command;

use Fearpro13\RemoteAuxControl\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppInitCommand extends Command
{
    protected static $defaultName = "app:init";
    protected static $defaultDescription = "Prepare config for application";

    public function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, "Current node name");
        $this->addArgument('secret', InputArgument::REQUIRED, "Telegram bot secret token");
        $this->addArgument(
            'chat_id',
            InputArgument::REQUIRED,
            "Telegram chat id where communication occurs. If first symbol is hyphen(-) - it must be replaced with underscore(_) !"
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = [
            'name' => $input->getArgument('name'),
            'secret' => $input->getArgument('secret'),
            'chat_id' => (int)str_replace("_", "-", $input->getArgument('chat_id'))
        ];

        $configFile = json_encode($config);

        if (!is_string($configFile)) {
            $output->writeln(App::APP_CANT_PREPARE_CONFIG);

            return Command::FAILURE;
        }

        $status = file_put_contents(App::CONFIG_PATH, $configFile);

        if (!$status) {
            $output->writeln(App::APP_CANT_SAVE_CONFIG);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}