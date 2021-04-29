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

namespace Clouria\IslandArchitect\extended\pocketmine;

use pocketmine\math\Vector3;
use pocketmine\level\generator\Generator;

final class DummyWorldGenerator extends Generator {

    public const GENERATOR_NAME = 'isarch-generator';

    public function __construct(array $settings = []) { }

    public function generateChunk(int $chunkX, int $chunkZ) : void {
        // $this->level->getChunk($chunkX, $chunkZ)->setGenerated(true);
    }

    public function populateChunk(int $chunkX, int $chunkZ) : void { }

    public function getSettings() : array {
        return [];
    }

    public function getName() : string {
        return self::GENERATOR_NAME;
    }

    public function getSpawn() : Vector3 {
        return new Vector3(0, 0, 0);
    }
}