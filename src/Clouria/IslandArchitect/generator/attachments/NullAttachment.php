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

namespace Clouria\IslandArchitect\generator\attachments;

use pocketmine\math\Vector3;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\structure\StructureData;

class NullAttachment implements StructureAttachment {

    public function run(StructureData $data, int &$pointer, Vector3 $pos, int $repeat) {
        self::parse($data, self::getIdentifier(), 0, $pointer);
    }

    public static function parse(StructureData $data, string $identifier, int $length, int &$pointer) : StructureAttachment {
        throw new \RuntimeException('Structure attachment "' . $identifier . '" is used but is not registered, is it from a different plugin?');
    }

    public static function getIdentifier() : string {
        return IslandArchitect::PLUGIN_NAME . ':null';
    }
}