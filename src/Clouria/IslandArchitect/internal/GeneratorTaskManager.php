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

namespace Clouria\IslandArchitect\internal;

use pocketmine\Server;
use pocketmine\level\Level;
use pocketmine\utils\Random;
use pocketmine\level\format\Chunk;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\StructureGeneratorTask;
use Clouria\IslandArchitect\generator\structure\StructureType;

final class GeneratorTaskManager {
    protected static $levelstructure = []; // Use protected to avoid BC break as possible

    /**
     * @param Level|string $level
     */
    public static function getLevelStructureType($level) : ?StructureType {
        if ($level instanceof Level) $level = $level->getFolderName();
        $type = self::$levelstructure[$level] ?? null;
        if ($type instanceof StructureType) return $type->validateAndReturn();
        return null;
    }

    public static function setLevelStructureType($level, StructureType $type) : bool {
        if ($level instanceof Level) $level = $level->getFolderName();
        if (isset(self::$levelstructure[$level]) or !$type->validate()) return false;
        self::$levelstructure[$level] = $type;
        return true;
    }

    public static function generateChunkForLevel(Level $level, Chunk $chunk) {
        $class = IslandArchitect::getInstance()->getStructureGeneratorTaskClass();
        /**
         * @see StructureGeneratorTask::__construct()
         */
        Server::getInstance()->getAsyncPool()->submitTask(new $class(
            new Random($level->getProvider()->getSeed()),
            $level,
            $chunk
        ));
    }

}