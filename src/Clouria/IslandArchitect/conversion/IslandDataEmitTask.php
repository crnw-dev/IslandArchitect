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
    level\format\Chunk,
    Server,
    scheduler\AsyncTask,
    utils\Utils};

use Clouria\IslandArchitect\{
	IslandArchitect,
	runtime\TemplateIsland
};

use function serialize;
use function unserialize;
use function file_put_contents;
use function mkdir;

class IslandDataEmitTask extends AsyncTask {

	/**
	 * @var string (Serialized TemplateIsland object)
	 */
	protected $island;

	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string (Serialized Chunk[]|null)
	 */
	protected $chunks;

    /**
     * @param TemplateIsland $island
     * @param Chunk[]|null $chunks If the array is not empty, an export action will be taken instead of normal save action
     * @param \Closure|null $callback Compatible with <code>function() {}</code>
     */
	public function __construct(TemplateIsland $island, ?array $chunks, ?\Closure $callback = null) {
		$this->island = serialize($island);
		$this->chunks = serialize($chunks);
		$this->path = IslandArchitect::getInstance()->getConfig()->get('island-data-folder', IslandArchitect::getInstance()->getDataFolder() . 'islands/');

		$this->storeLocal([$callback]);
	}

	public function onRun() : void {
		$island = unserialize($this->island);
		$chunks = unserialize($this->chunks);

		if (empty($chunks ?? [])) $data = $island->save();
		else $data = $island->export($chunks);
		$path = Utils::cleanPath($this->path);
		@mkdir($path);
		$path = $path . ($path[-1] === '/' ? '' : '/') . $island->getName() . '.json';
		file_put_contents($path, $data);

		$this->setResult(null);
	}

	public function onCompletion(Server $server) : void {
		$callback = $this->fetchLocal()[0];
		if (isset($callback)) $callback();
	}
}