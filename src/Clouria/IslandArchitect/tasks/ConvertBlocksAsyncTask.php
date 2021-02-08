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
	Server,
	scheduler\AsyncTask,
	math\Vector3,
	utils\Utils
};

use function serialize;
use function unserialize;
use function basename;
use function asort;
use function explode;
use function json_encode;
use function file_put_contents;
use function mkdir;
use function dirname;
use function microtime;

use const SORT_NUMERIC;

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
	 * @param string $name The name of this island template, will also use as the JSON file name
	 * @param \pocketmine\level\format\Chunk[] $chunks
	 * @param array<mixed[]> $islanddata
	 * @param \Closure|null $callback Compatible with <code>function(float $time) {}</code>
	 */
	public function __construct(string $outputpath, string $name, Vector3 $pos1, Vector3 $pos2, array $chunks, array $islanddata = [], ?\Closure $callback = null) {
		$this->path = $outputpath;
		$this->name = $name;
		$this->pos1 = serialize($pos1);
		$this->pos2 = serialize($pos2);
		$this->chunks = serialize($chunks);
		$this->data = serialize($islanddata);

		$this->storeLocal([$callback]);
	}

	public function onRun() : void {
		$time = microtime(true);

		$pos1 = unserialize($this->pos1);
		$pos2 = unserialize($this->pos2);
		$chunksraw = unserialize($this->chunks);
		foreach ($chunksraw as $chunk) $chunks[$chunk->getX()][$chunk->getZ()] = $chunk;
		$data = unserialize($this->data);
		
		$data['version'] = $data['version'] ?? IslandData::VERSION;
		$data['name'] = $data['name'] ?? $this->name;

		$xl = [$pos1->getFloorX(), $pos2->getFloorX()];
		$yl = [$pos1->getFloorY(), $pos2->getFloorY()];
		$zl = [$pos1->getFloorZ(), $pos2->getFloorZ()];
		asort($xl, SORT_NUMERIC);
		asort($yl, SORT_NUMERIC);
		asort($zl, SORT_NUMERIC);

		for ($x = $xl[0]; $x <= $xl[1]; $x++) for ($z = $zl[0]; $z <= $zl[1]; $z++) {
			$chunk = $chunks[$x << 4][$z << 4];
			$x = (int)((($x / 16) - (int)($x / 16)) * 16);
			$z = (int)((($z / 16) - (int)($z / 16)) * 16);
			$meta = explode('', $chunk->getBlockDataColumn($x, $z));
			foreach (explode('', $chunk->getBlockIdColumn($x, $z)) as $y => $id) {
				if ($y < $yl[0]) continue;
				if ($y > $yl[1]) continue;
				$data['chunks'][($x << 4) - ($xl[0] << 4)][($z << 4) - ($zl[0] << 4)][$y] = $data['chunks'][($x << 4) - ($xl[0] << 4)][($z << 4) - ($zl[0] << 4)][$y] ?? (int)$id + ((int)$meta[$y] / 100);
			}
		}
		@mkdir(dirname(Utils::cleanPath($this->path)));
		file_put_contents(Utils::cleanPath($this->path), json_encode($data));

		$this->setResult([$time]);
	}

	public function onCompletion(Server $server) : void {
		$this->fetchLocal()[0]($this->getResult()[0]);
	}
}