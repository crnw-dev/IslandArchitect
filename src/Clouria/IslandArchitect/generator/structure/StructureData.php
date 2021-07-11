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

namespace Clouria\IslandArchitect\generator\structure;

use pocketmine\utils\Binary;
use Clouria\IslandArchitect\Utils;
use function fseek;
use function is_resource;

class StructureData {

    public const FORMAT_VERSION = 0;

    /**
     * @var resource
     */
    public $stream;

    /**
     * @var int
     */
    public $chunkhash;

    /**
     * @throws StructureParseException
     */
    public function decode() {
        if (!is_resource($this->stream)) return;
        fseek($this->stream, 0);
        $header = Utils::ReadAndSeek($this->stream, 3);
        // Format version (1), sub version (1), chunk hash size (1)

        $ver = Binary::readByte($header[0]);
        if ($ver > self::FORMAT_VERSION) $this->panicParse("Unsupported structure format version " . $ver, false);

        $chashsize = [
                         0 => 1,
                         1 => 2,
                         3 => 4,
                         4 => 8,
                     ][$header[3]];
        $chunkmap = Utils::ReadAndSeek($this->stream, $chashsize * 8);
        // An array of chunk hash followed by file offset (8), max limit 1MB per chunk

        for ($cpointer = 0; $cpointer < $chunkmap / ($chashsize + 8); $chunkmap += ($chashsize + 8)) {
            // TODO: Read data with correct range by its length
        }
    }

    /**
     * @throws StructureParseException
     */
    protected function panicParse(string $err, bool $corrupted = true) : void {
        if ($corrupted) $err .= ", is the structure data file occupied?";
        throw new StructureParseException($this->stream, "", $err);
    }

}