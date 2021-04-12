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
namespace Clouria\IslandArchitect\generator;

use room17\SkyBlock\island\generator\IslandGenerator;
use pocketmine\{
    item\Item,
    level\Level,
    math\Vector3
};
use function unserialize;

class TemplateIslandGenerator extends IslandGenerator {

    public const GENERATOR_NAME = 'templateislandgenerator';

    /**
	 * @var TemplateIsland|null
	 */
	protected $island = null;

    public function __construct(array $settings = []) {
        parent::__construct($settings);

	    $island = TemplateIsland::load(unserialize($this->getSettings()['preset'])[0]);
        if ($island === null) throw new \RuntimeException('Cannot pass template island instance into the generator thread');
        $this->island = $island;
	}

    public function generateChunk(int $chunkX, int $chunkZ) : void {
		$chunk = $this->level->getChunk($chunkX, $chunkZ);
        $chunk->setGenerated();
        for ($x=0; $x < 16; $x++) for ($z=0; $z < 16; $z++) for ($y=0; $y <= Level::Y_MAX; $y++) {
            $block = $this->island->getProcessedBlock(($chunk->getX() << 4) + $x, $y, ($chunk->getZ() << 4) + $z, $this->random);
            if ($block === null or $block[0] === Item::AIR) continue;
            $chunk->setBlock($x, $y, $z, (int)$block[0], (int)$block[1]);
        }
	}

	public function getName() : string {
		return isset($this->island) ? $this->island->getName() : 'TemplateIslandGenerator';
	}

    public function populateChunk(int $chunkX, int $chunkZ) : void {}

    public function getSpawn() : Vector3 {
        return $this->island->getSpawn();
    }

    public static function getWorldSpawn() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public static function getChestPosition() : Vector3 {
        return new Vector3(0, 0, 0);
    }
}
