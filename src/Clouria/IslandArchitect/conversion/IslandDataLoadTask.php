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
namespace Clouria\IslandArchitect\conversion;

use pocketmine\{
	Server,
	scheduler\AsyncTask,
	utils\Utils
};

use Clouria\IslandArchitect\runtime\TemplateIsland;

use function serialize;
use function unserialize;
use function file_get_contents;
use function is_file;

class IslandDataEmitTask extends AsyncTask {

	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $islandname;

	/**
	 * @param \Closure|null $callback Compatible with <code>function(?<@link TemplateIsland> $island, string $filepath) {}</code>
	 */
	public function __construct(string $islandname, ?\Closure $callback = null) {
		$this->islandname = $islandname;
		$this->path = IslandArchitect::getInstance()->getConfig()->get('island-data-folder', IslandArchitect::getInstance()->getDataFolder() . 'islands/');

		$this->storeLocal([$callback]);
	}

	public function onRun() : void {
		if (
			is_file($ppath = ($spath = Utils::cleanPath($this->islandname))) or // ppath = Primary path (Don't question lol)
			is_file($spath = Utils::cleanPath($this->islandname) . '.json') or
			is_file($spath = Utils::cleanPath($this->path) . ($this->path[-1] === '/' ? '' : '/') . $this->islandname . '.json')
		) $this->setResult([serialize(TemplateIsland::load(file_get_contents($spath))), $ppath]);
		else $this->setResult([serialize(null), $ppath]);
	}

	public function onCompletion(Server $server) : void {
		$r = $this->getResult();
		$this->fetchLocal()[0](unserialize($r[0]), $r[1]);
	}
}