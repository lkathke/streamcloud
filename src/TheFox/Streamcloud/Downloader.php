<?php

namespace TheFox\Streamcloud;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client as GuzzleHttpClient;
#use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Event\ProgressEvent;
use Zend\Uri\UriFactory;
#use Zend\Uri\Uri;

class Downloader{
	
	const WAIT_TIME = 12;
	
	private $log = null;
	private $url = '';
	private $client = null;
	
	public function __construct(){
		$this->client = $this->createGuzzleHttpClient();
	}
	
	public function setLog($log){
		$this->log = $log;
	}
	
	public function getLog(){
		return $this->log;
	}
	
	public function setUrl($url){
		$this->url = $url;
	}
	
	public function run(){
		$this->log->info('start');
		$this->log->info('url: '.$this->url);
		
		$status = 0;
		
		$fileName = '';
		$uri = UriFactory::factory($this->url);
		if($uri){
			$pathInfo = pathinfo($uri->getPath());
			if(isset($pathInfo['filename']) && $pathInfo['filename']){
				$fileName = $pathInfo['filename'];
			}
		}
		
		$this->log->debug('filename: '.$fileName);
		
		if($fileName){
			$response = null;
			try{
				$this->log->debug('get url "'.$this->url.'"');
				$response = $this->client->get($this->url);
				#$response = $this->client->get($this->url, array('save_to' => 'data1.html'));
			}
			catch(Exception $e){
				$this->log->error('url failed, "'.$this->url.'": '.$e->getMessage());
			}
			
			if($response){
				if($response->getStatusCode() == 200){
					$this->log->debug(' -> code: '.$response->getStatusCode());
					
					$body = (string)$response->getBody();
					$bodyLen = strlen($body);
					$this->log->debug(' -> len: '.$bodyLen);
					
					#$dom = DOMDocument::loadHTML($response->getBody());
					#\Doctrine\Common\Util\Debug::dump($dom);
					#\Doctrine\Common\Util\Debug::dump((string)$response);
					
					$inputs = array();
					
					$htmlDoc = new DOMDocument();
					$htmlDoc->loadHTML($body);
					$htmlDocXpath = new DOMXPath($htmlDoc);
					$htmlDocXpathList = $htmlDocXpath->query('//form/input');
					foreach($htmlDocXpathList as $input){
						#\Doctrine\Common\Util\Debug::dump($input, 10);
						
						$name = $input->getAttribute('name');
						$value = $input->getAttribute('value');
						#$this->log->debug(' -> input: '.$name.' /'.$value.'/');
						$inputs[$name] = $value;
					}
					
					#\Doctrine\Common\Util\Debug::dump($inputs);
					
					if($inputs){
						if(static::WAIT_TIME){
							$this->log->debug('wait '.static::WAIT_TIME);
							sleep(static::WAIT_TIME);
						}
						
						$response = null;
						try{
							$this->log->debug('get url "'.$this->url.'"');
							$response = $this->client->post($this->url, array(
								'headers' => array(
									'Referer' => $this->url,
								),
								'body' => $inputs,
								#'save_to' => 'data2.html',
							));
						}
						catch(Exception $e){
							$this->log->error('url failed, "'.$this->url.'": '.$e->getMessage());
						}
						
						if($response){
							if($response->getStatusCode() == 200){
								$this->log->debug(' -> code: '.$response->getStatusCode());
								
								$body = (string)$response->getBody();
								$bodyLen = strlen($body);
								$this->log->debug(' -> len: '.$bodyLen);
								
								if(preg_match('/jwplayer/i', $body)){
									if(preg_match('/file. .(http:...{1,10}.streamcloud.eu:8080[^"]+)"/', $body, $res)){
										#\Doctrine\Common\Util\Debug::dump($res);
										
										$videoUrl = $res[1];
										
										$response = null;
										try{
											$this->log->debug('get video "'.$videoUrl.'"');
											$requestOptions = array(
												'headers' => array(
													'Referer' => $this->url,
												),
												'timeout' => 7200,
												'save_to' => 'videos/'.$fileName,
											);
											#$response = $this->client->get($videoUrl, $requestOptions);
											
											$request = $this->client->createRequest('GET', $videoUrl, $requestOptions);
											#\Doctrine\Common\Util\Debug::dump($request);

											$request->getEmitter()->on('progress', function(ProgressEvent $e){
												$percent = 0;
												if($e->downloadSize){
													$percent = sprintf('%5.2f', $e->downloaded / $e->downloadSize);
												}
												$points = (int)($percent / 20);
												print ' '.$percent.' %  ['.str_repeat('#', $points).str_repeat(' ', 20 - $points).']'."\r";
											});

											$response = $this->client->send($request);
										}
										catch(Exception $e){
											$this->log->error('url failed, "'.$videoUrl.'": '.$e->getMessage());
										}
										
										if($response){
											if($response->getStatusCode() == 200){
												$this->log->debug(' -> code: '.$response->getStatusCode());
											}
											else{
												$status = 10;
												$this->log->error('wrong status code: '.$response->getStatusCode());
											}
										}
										else{
											$status = 9;
											$this->log->error('no response [C]');
										}
									}
									else{
										$status = 8;
										$this->log->error('no url found in javascript');
									}
								}
								else{
									$status = 7;
									$this->log->error('no jwplayer found');
								}
							}
							else{
								$status = 6;
								$this->log->error('wrong status code: '.$response->getStatusCode());
							}
						}
						else{
							$status = 5;
							$this->log->error('no response [B]');
						}
					}
					else{
						$status = 4;
						$this->log->error('no inputs found');
					}
				}
				else{
					$status = 3;
					$this->log->error('wrong status code: '.$response->getStatusCode());
				}
			}
			else{
				$status = 2;
				$this->log->error('no response [A]');
			}
		}
		else{
			$status = 1;
			$this->log->error('no filename found');
		}
		
		$this->log->info('end');
		
		return $status;
	}
	
	private function createGuzzleHttpClient(){
		$userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) ';
		$userAgent .= 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.65 Safari/537.36';
		$this->cookieJar = new FileCookieJar('cookies/cookies.txt');
		$clientOptions = array(
			'headers' => array(
				'User-Agent' => $userAgent,
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Encoding' => 'gzip',
				'Accept-Language' => 'en-US,en;q=0.8,de-AT;q=0.6,de;q=0.4',
			),
			'connect_timeout' => 3,
			'timeout' => 5,
			'verify' => false,
			'cookies' => $this->cookieJar,
		);
		$client = new GuzzleHttpClient(array('defaults' => $clientOptions));
		
		#\Doctrine\Common\Util\Debug::dump($client);
		
		return $client;
	}
	
}
