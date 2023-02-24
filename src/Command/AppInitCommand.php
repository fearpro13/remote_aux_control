<?php

namespace Fearpro13\RemoteAuxControl\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppInitCommand extends Command
{
    protected static $defaultName = "app:init";
    protected static $defaultDescription = "Init application";

    public function configure(): void
    {
        $this->addArgument('node_name', InputArgument::REQUIRED, "Название удалённого узла");
        $this->addArgument('tg_secret', InputArgument::REQUIRED, "Токен доступа телеграм API");
        $this->addArgument('tg_chat_id', InputArgument::REQUIRED, "ID чата в котором происходит взаимодействие. Замените дефис на нижнее подчёркивание!");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $remoteNodeName = $input->getArgument('node_name');
        $secret = $input->getArgument('tg_secret');
        $chatId = $input->getArgument('tg_chat_id');

        $rootDir = ROOT_DIR;
        $configFilePath = "$rootDir/../config.json";

        $config = [
            'node_name' => $remoteNodeName,
            'secret' => $secret,
            'chat_id' => (int)str_replace("_", "-", $chatId)
        ];

        $configFile = json_encode($config);

        $status = file_put_contents($configFilePath, $configFile);

        if (!$status) {
            $output->writeln("Неудачно");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}