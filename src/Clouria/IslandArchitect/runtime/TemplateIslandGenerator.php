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
namespace Clouria\IslandArchitect\runtime;

use pocketmine\{
    math\Vector3,
    utils\Utils,
    block\Block,
    level\generator\Generator as GeneratorInterface};

use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\customized\CustomizableClassTrait;

use function unserialize;
use function is_file;
use function file_get_contents;

class TemplateIslandGenerator extends GeneratorInterface {
    use CustomizableClassTrait;

    public const GENERATOR_NAME = 'templateislandgenerator';

    /**
	 * @var TemplateIsland|null
	 */
	protected $island = null;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var string
     */
    protected $pathprefix;

    public function __construct(array $settings = []) {
        $this->pathprefix = (string)$conf->get('island-data-folder', IslandArchitect::getInstance()->getDataFolder() . 'islands/');
	    $this->settings = $settings;
	}

    public function generateChunk(int $chunkX, int $chunkZ) : void {
		$chunk = $this->level->getChunk($chunkX, $chunkZ);
        $chunk->setGenerated();
        if (!isset($this->island)) {
	        $rpath = unserialize($this->getSettings()['preset'])[0];
			if (
			    (!is_file($path = Utils::cleanPath($rpath))) and
                (!is_file($path = ($prefix = Utils::cleanPath($prefix)) . ($prefix[-1] === '/' ? '' : '/') . $rpath))
            ) throw new \RuntimeException('Island data file (' . $path . ') is missing');
			$island = TemplateIsland::load(file_get_contents($path));
			if ($island === null) throw new \RuntimeException('Island "' . basename($path, '.json') . '"("' . $path . '") failed to load');
			$this->island = $island;
		}
		foreach ($this->island->getChunkBlocks($chunk->getX(), $chunk->getZ(), $this->random) as $x => $xd) foreach ($xd as $z => $zd) foreach ($zd as $y => $yd) {
			if ((int)$yd[0] === Block::AIR) continue;
			$chunk->setBlock((int)$x, (int)$y, (int)$z, (int)$yd[0], (int)$yd[1]);
		}
	}

	public function getName() : string {
		return isset($this->island) ? $this->island->getName() : 'TemplateIslandGenerator';
	}

    public function populateChunk(int $chunkX, int $chunkZ) : void {}

    public function getSettings() : array {
        return $this->settings;
    }

    public function getSpawn() : Vector3 {
        return new Vector3(0, 0, 0);
    }
}
