<?php

namespace TheFox\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use TheFox\Streamcloud\Downloader;

/**
 * @codeCoverageIgnore
 */
class DownloadCommand extends BasicCommand{
	
	public function getLogfilePath(){
		return 'log/download.log';
	}
	
	public function getPidfilePath(){
		return 'pid/download.pid';
	}
	
	protected function configure(){
		$this->setName('download');
		$this->setDescription('Download a file from url.');
		$this->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'URL');
		$this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force download even if errors occur.');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output){
		$status = 0;
		$this->executePre($input, $output);
		
		if($input->hasOption('url') && $input->getOption('url')){
			$downloader = new Downloader();
			$downloader->setLog($this->log);
			$downloader->setUrl($input->getOption('url'));
			$status = $downloader->run();
		}
		
		$this->executePost();
		
		return $status;
	}
	
	public function signalHandler($signal){
		$this->exit++;
		
		switch($signal){
			case SIGTERM:
				$this->log->notice('signal: SIGTERM');
				break;
			case SIGINT:
				print PHP_EOL;
				$this->log->notice('signal: SIGINT');
				break;
			case SIGHUP:
				$this->log->notice('signal: SIGHUP');
				break;
			case SIGQUIT:
				$this->log->notice('signal: SIGQUIT');
				break;
			case SIGKILL:
				$this->log->notice('signal: SIGKILL');
				break;
			case SIGUSR1:
				$this->log->notice('signal: SIGUSR1');
				break;
			default:
				$this->log->notice('signal: N/A');
		}
		
		$this->log->notice('main abort ['.$this->exit.']');
		
		if($this->exit >= 2){
			exit(1);
		}
	}
	
}
