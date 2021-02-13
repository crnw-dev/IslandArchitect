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
namespace Clouria\IslandArchitect\api;

use funciton array_push;

class TemplateIsland {

	/**
	 * @return RandomGeneration[]
	 */
	public function getRandoms() : array {
		return $this->randoms;
	}

	public function getRandomById(int $id) : ?RandomGeneration {
		return $this->randoms[$id];
	}

	public function removeRandomById(int $id) : bool {
		if (!isset($this->randoms[$id])) return false;
		unset($this->randoms[$id]);
		return true;
	}

	/**
	 * @param RandomGeneration $random
	 * @return int The random generation regex ID
	 */
	public function attachRandom(RandomGeneration $random) : int {
		return array_push($this->randoms, $random) - 1;
	}
}