<?php

namespace TheFox\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Liip\ProcessManager\ProcessManager;
use Liip\ProcessManager\PidFile;

use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;

/**
 * @codeCoverageIgnore
 */
class BasicCommand extends Command{
	
	public $log;
	public $exit = 0;
	private $pidFile;
	private $settings;
	
	public function setExit($exit){
		$this->exit = $exit;
	}
	
	public function getExit(){
		return $this->exit;
	}
	
	public function getLogfilePath(){
		return 'log/application.log';
	}
	
	public function getPidfilePath(){
		return 'pid/application.pid';
	}
	
	public function executePre(InputInterface $input, OutputInterface $output){
		$this->log = new Logger($this->getName());
		$this->log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		$this->log->pushHandler(new StreamHandler($this->getLogfilePath(), Logger::DEBUG));
		
		if($input->hasOption('shutdown') && $input->getOption('shutdown')){
			if(file_exists($this->getPidfilePath())){
				$pid = file_get_contents($this->getPidfilePath());
				$this->log->info('kill '.$pid);
				posix_kill($pid, SIGTERM);
			}
			exit();
		}
		elseif($input->hasOption('daemon') && $input->getOption('daemon')){
			if(function_exists('pcntl_fork')){
				$pid = pcntl_fork();
				if($pid < 0 || $pid){
					exit();
				}
				
				$sid = posix_setsid();
				$this->signalHandlerSetup();
				
				$pid = pcntl_fork();
				if($pid < 0 || $pid){
					exit();
				}
				
				umask(0);
				
				$this->stdStreamsSetup();
			}
		}
		else{
			$this->signalHandlerSetup();
		}
		
		$this->pidFile = new PidFile(new ProcessManager(), $this->getPidfilePath());
		$this->pidFile->acquireLock();
		$this->pidFile->setPid(getmypid());
	}
	
	public function executePost(){
		$this->pidFile->releaseLock();
	}
	
	public function signalHandlerSetup(){
		if(function_exists('pcntl_signal')){
			declare(ticks = 1);
			pcntl_signal(SIGTERM, array($this, 'signalHandler'));
			pcntl_signal(SIGINT, array($this, 'signalHandler'));
			pcntl_signal(SIGHUP, array($this, 'signalHandler'));
		}
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
	
	private function stdStreamsSetup(){
		global $STDIN, $STDOUT, $STDERR;
		
		fclose(STDIN);
		fclose(STDOUT);
		fclose(STDERR);
		$STDIN = fopen('/dev/null', 'r');
		$STDOUT = fopen('/dev/null', 'wb');
		$STDERR = fopen('/dev/null', 'wb');
	}
	
}
