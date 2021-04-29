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
		
        ██╗  ██╗    ██╗  ██╗    
        ██║  ██║    ██║ ██╔╝    光   時   LIBERATE   
        ███████║    █████╔╝     復   代   HONG
        ██╔══██║    ██╔═██╗     香   革   KONG
        ██║  ██║    ██║  ██╗    港   命   
        ╚═╝  ╚═╝    ╚═╝  ╚═╝
                    
														*/
declare(strict_types=1);

namespace Clouria\IslandArchitect\extended\skyblock;

use pocketmine\math\Vector3;
use room17\SkyBlock\island\generator\IslandGenerator;
use Clouria\IslandArchitect\extended\pocketmine\DummyWorldGenerator;

final class DummyIslandGenerator extends IslandGenerator {

    public const GENERATOR_NAME = DummyWorldGenerator::GENERATOR_NAME;
    public const LEGACY_GENERATOR_NAME = 'templateislandgenerator';

    public static function getWorldSpawn() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public static function getChestPosition() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public function generateChunk(int $chunkX, int $chunkZ) : void {
        // $this->level->getChunk($chunkX, $chunkZ)->setGenerated(true);
    }

    public function getName() : string {
        return self::GENERATOR_NAME;
    }
}