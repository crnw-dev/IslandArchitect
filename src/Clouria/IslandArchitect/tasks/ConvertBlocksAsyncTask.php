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
namespace Clouria\IslandArchitect\tasks;

use pocketmine\{
	scheduler\AsyncTask,
	math\Vector3
};

use function serialize;
use function unserialize;

class ConvertBlockAsyncTask extends AsyncTask {

	/**
	 * @var Vector3
	 */
	protected $pos1;
	
	/**
	 * @var Vector3
	 */
	protected $pos2;

	/**
	 * @var \pocketmine\level\format\Chunk[]
	 */
	protected $chunks;

	/**
	 * @param \pocketmine\level\format\Chunk[] $chunks
	 */
	public function __construct(Vector3 $pos1, Vector3 $pos2, array $chunks) {
		$this->pos1 = serialize($pos1);
		$this->pos2 = serialize($pos2);
		$this->chunks = serialize($chunks);
	}

	public function onRun() : void {
		$pos1 = unserialize($this->pos1);
		$pos2 = unserialize($this->pos2);
		$chunks = unserialize($this->chunks);
	}
}