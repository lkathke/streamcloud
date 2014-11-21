#!/usr/bin/env php
<?php

require_once __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

use TheFox\Streamcloud\Streamcloud;
use TheFox\Console\Command\InfoCommand;
use TheFox\Console\Command\DownloadCommand;


$application = new Application(Streamcloud::NAME, Streamcloud::VERSION);
$application->add(new InfoCommand());
$application->add(new DownloadCommand());
$application->run();
