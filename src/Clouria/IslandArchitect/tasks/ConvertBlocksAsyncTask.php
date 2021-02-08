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
use function basename;

class ConvertBlockAsyncTask extends AsyncTask {

	/**
	 * @var string
	 */
	protected $path;
	
	/**
	 * @var string
	 */
	protected $name;
	
	protected $data;
	protected $pos1;
	protected $pos2;
	protected $chunks;

	/**
	 * @param \pocketmine\level\format\Chunk[] $chunks
	 */
	public function __construct(string $outputpath, string $name, Vector3 $pos1, Vector3 $pos2, array $chunks, array $islanddata = []) {
		$this->path = $outputpath;
		$this->name = $name;
		$this->pos1 = serialize($pos1);
		$this->pos2 = serialize($pos2);
		$this->chunks = serialize($chunks);
		$this->data = serialize($islanddata);
	}

	public function onRun() : void {
		$pos1 = unserialize($this->pos1);
		$pos2 = unserialize($this->pos2);
		$chunksraw = unserialize($this->chunks);
		foreach ($chunksraw as $chunk) $chunks[$chunk->getX()][$chunk->getZ()] = $chunk;
		$data = unserialize($this->data);
		
		$data['version'] = $data['version'] ?? IslandData::VERSION;
		$data['name'] = $data['name'] ?? $this->name;
		
	}
}