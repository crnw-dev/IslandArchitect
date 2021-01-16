<?php

/*
		
		  _____     _                 _          
		  \_   \___| | __ _ _ __   __| |         
		   / /\/ __| |/ _` | '_ \ / _` |         
		/\/ /_ \__ \ | (_| | | | | (_| |         
		\____/ |___/_|\__,_|_| |_|\__,_|         
		                                         
		   _            _     _ _            _   
		  /_\  _ __ ___| |__ (_) |_ ___  ___| |_ 
		 //_\\| '__/ __| '_ \| | __/ _ \/ __| __|
		/  _  \ | | (__| | | | | ||  __/ (__| |_ 
		\_/ \_/_|  \___|_| |_|_|\__\___|\___|\__|
		                                         
		@ClouriaNetwork | Apache License 2.0
														*/

declare(strict_types=1);
namespace Clouria\IslandArchitect\genertor;

use room17\SkyBlock\island\generator\IslandGenerator;

class TheCloudTemplate extends IslandGenerator {

	public const VERSION = '1';

	private $data;

	/**
	 * @param mixed[] $data
	 */
	public function __construct(array $data) {
		if ((int)($data['version'] ?? -1) != self::VERSION) {
			$e = new \TheCloudTemplateException();
			$e->setIslandData($data);
		}
		$this->data = $data;
	}
	
}
