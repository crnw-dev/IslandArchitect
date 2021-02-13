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

use pocketmine\{
	math\Vector3
};

use funciton array_push;

class TemplateIsland {

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $orginalName;

	public function getName() : string {
		return $this->name;
	}

	public function setName(string $name) : void {
		$this->name = $name;
	}

	/**
	 * @var Vector3
	 */
	protected $startcoord = null;

	public function getStartCoord() : ?Vector3 {
		return $this->startcoord;
	}

	public function setStartCoord(Vector3 $pos) : void {
		return $this->startcoord = $pos;
	}

	/**
	 * @var Vector3
	 */
	protected $endcoord = null;

	public function getEndCoord() : ?Vector3 {
		return $this->endcoord;
	}

	public function setEndCoord(Vector3 $pos) : void {
		return $this->endcoord = $pos;
	}

	/**
	 * @var Level
	 */
	protected $level = null;
	
	public function getLevel() : ?Level {
		return $this->level;
	}

	public function setLevel(Level $level) : void {
		return $this->level = $level;
	}

	/**
	 * @var RandomGeneration[]
	 */
	protected $randoms = [];

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
	public function addRandom(RandomGeneration $random) : int {
		return array_push($this->randoms, $random) - 1;
	}

	public function encode() : void {
		$data['version'] = self::VERSION;
		$data['name'] = $this->getName();
		$data['startcoord'] = $this->getStartCoord();
		$data['endcoord'] = $this->getEndCoord();

		foreach ($this->randoms as $random) $randoms[] = $random->getAllElements();
		$data['randoms'] = $randoms ?? [];
	}
}