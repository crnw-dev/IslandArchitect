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

namespace Clouria\IslandArchitect\customized\skyblock;

use pocketmine\math\Vector3;
use room17\SkyBlock\island\generator\IslandGenerator;
use Clouria\IslandArchitect\{
    runtime\TemplateIslandGenerator,
    customized\CustomizableClassTrait};

class DummyIslandGenerator extends IslandGenerator {
    use CustomizableClassTrait;

    public const GENREATOR_NAME = 'dummyislandgenerator';

    public static function getWorldSpawn() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public static function getChestPosition() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public function generateChunk(int $chunkX, int $chunkZ) : void {}

    public function getName() : string {
        $class = TemplateIslandGenerator::getClass();
        assert(is_a($class, TemplateIslandGenerator::class, true));
        return $class::GENERATOR_NAME;
    }
}