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

final class NullAttachment implements StructureAttachment {

    private function __construct() { }

    public static function getIdentifier() : string {
        return IslandArchitect::PLUGIN_NAME . ':null';
    }

    public static function newUnloaded(string $identifier) : StructureAttachment {
        throw new \RuntimeException('Structure attachment "' . $identifier . '" is used but is not registered, is it from a different plugin?');
    }

    public function isLoaded() : bool {
        throw new \RuntimeException('This line of code is supposed to be reachable');
    }

    public function run(StructureData $data, int &$pointer, Vector3 $pos, int $repeat) {
        throw new \RuntimeException('This line of code is supposed to be reachable');
    }
}