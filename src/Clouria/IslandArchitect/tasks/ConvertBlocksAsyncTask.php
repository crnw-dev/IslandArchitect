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
	scheduler\AsyncTask
};

use function serialize;
use function unserialize;

class ConvertBlockAsyncTask extends AsyncTask {

	/**
	 * @var \pocketmine\level\format\Chunk[]
	 */
	protected $chunks;

	/**
	 * @param \pocketmine\level\format\Chunk[] $chunks
	 */
	public function __construct(array $chunks) {
		$this->chunks = serialize($chunks);
	}

	public function onRun() : void {
		$chunks = unserialize($this->chunks);
	}
}