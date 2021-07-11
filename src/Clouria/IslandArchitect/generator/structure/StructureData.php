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
use function abs;
use function fseek;
use function substr;
use function is_resource;
use const SEEK_CUR;
use const PHP_INT_MAX;

class StructureData {

    public const FORMAT_VERSION = 0;
    public const CHUNKMAP_ELEMENT_SIZE = 10;

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
        $header = Utils::ReadAndSeek($this->stream, 4);
        // Format version (1), sub version (1), chunks count (2)

        $ver = Binary::readByte($header[0]);
        if ($ver > self::FORMAT_VERSION) $this->panicParse("Unsupported structure format version " . $ver, false);

        $ccount = Binary::readSignedLShort(substr($header, 2));
        $cmap = Utils::ReadAndSeek($this->stream, self::CHUNKMAP_ELEMENT_SIZE * $ccount);
        // An array (32) of chunk hash (2) followed by chunk length (8), max limit 2MB per chunk
        unset($header, $ver);

        for ($cpointer = 0; $cpointer < $ccount * self::CHUNKMAP_ELEMENT_SIZE; $cpointer += self::CHUNKMAP_ELEMENT_SIZE) {
            $cmapped = substr($cmap, $cpointer, self::CHUNKMAP_ELEMENT_SIZE);
            $mappedlen = Binary::readLLong(substr($cmapped, 2));
            $mappedhash = Binary::readSignedLShort(substr($cmapped, 0, 2));
            if ($mappedhash < 0) $mappedhash += 2147483647 - $mappedhash - 1;
            if ($mappedhash === $this->chunkhash) {
                $clen = $mappedlen;
                break;
            }

            if ($mappedlen < 0) fseek($this->stream, PHP_INT_MAX, SEEK_CUR);
            fseek($this->stream, (int)(abs($mappedlen) - ($mappedlen < 0 ? 1 : 0)), SEEK_CUR);
        }
        unset($cmap, $cpointer, $ccount, $cmapped, $mappedlen, $mappedhash);

        do {
            $junction = Utils::ReadAndSeek($this->stream, 3);
            // Part expend type (1), signed part length (2)
            $ptype = Binary::readByte($junction[0]);
            $plen = Binary::readSignedLShort(substr($junction, 1));
            if ($plen < 0) $plen += 32767 - $plen - 1;
            $pdata = Utils::ReadAndSeek($this->stream, $plen);
            $clen -= 3 + $plen;
        } while ($clen > 0);
    }

    /**
     * @throws StructureParseException
     */
    protected function panicParse(string $err, bool $corrupted = true) : void {
        if ($corrupted) $err .= ", is the structure data file occupied?";
        throw new StructureParseException($this->stream, "", $err);
    }

}