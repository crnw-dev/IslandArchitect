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
use Clouria\IslandArchitect\generator\structure\StructureData;

interface StructureAttachment {

    /**
     * @return static
     */
    public static function newUnloaded(string $identifier) : self;

    public function isLoaded() : bool;

    /**
     * @param StructureData $data
     * @param int $pointer Pointer of the stream (file descriptor / websocket...) in StructureData
     * @param Vector3 $pos Current block position in chunk (0-15, 0-255, 0-15)
     * @param int $repeat Count of remaining repeat cycles
     * @return mixed
     */
    public function run(StructureData $data, int &$pointer, Vector3 $pos, int $repeat);

    /**
     * @return string The identifier should include a fallback prefix and the attachment name and MUST have less than 256 (single-byte) characters
     */
    public static function getIdentifier() : string;

}