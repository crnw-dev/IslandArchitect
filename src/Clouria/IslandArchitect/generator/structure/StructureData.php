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

use pocketmine\level\Level;
use pocketmine\utils\Binary;
use Clouria\IslandArchitect\Utils;
use function abs;
use function fseek;
use function substr;
use function strlen;
use function is_resource;
use const SEEK_CUR;
use const PHP_INT_MAX;

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
        $header = Utils::ReadAndSeek($this->stream, 11);
        // Format version (1), sub version (1), extended blocks (1), signed length of first chunk (8)

        $ver = Binary::readByte($header[0]);

        if ($ver > self::FORMAT_VERSION) $this->panicParse("Unsupported structure format version " . $ver, false);
        $extendedbk = Binary::readByte($header[2]) === 1;

        $clength = substr($header, 3);
        if (strlen($clength) !== 8) return; // Empty structure
        $clength = Binary::readLLong($clength);

        Level::getXZ($this->chunkhash, $cx, $cz);
        for ($x = 0; $x <= $cx; $x++) for ($z = 0; $z <= $cz; $z++) {
            if ($clength < 0) fseek($this->stream, PHP_INT_MAX, SEEK_CUR); // Max limit 2MB per chunk
            fseek($this->stream, (int)(abs($clength) - ($clength < 0 ? 1 : 0)), SEEK_CUR);
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