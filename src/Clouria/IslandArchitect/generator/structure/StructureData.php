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
use pocketmine\utils\Random;
use Clouria\IslandArchitect\Utils;
use pocketmine\level\format\Chunk;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\properties\StructureProperty;
use function abs;
use function fseek;
use function substr;
use function strlen;
use const SEEK_CUR;

class StructureData {

    public const FORMAT_VERSION = 0;
    public const CHUNKMAP_ELEMENT_SIZE = 10;
    public const Y_MAX = 0x100; // Hardcode Y max or breaks block filling after Y is has increased

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var Chunk
     */
    protected $chunk;

    /**
     * @var Random
     */
    protected $random;

    /**
     * @var array<string, StructureProperty>
     */
    protected $properties;

    /**
     * @throws StructureParseException
     */
    public function decode() {
        fseek($this->stream, 2);

        $propmlen = Utils::readAndSeek($this->stream, 4);
        if (strlen($propmlen) !== 4) $this->panicParse("File ends before property map");
        $propmlen = Binary::readLInt($propmlen);
        if ($propmlen < 0) fseek($this->stream, 2147483647);
        fseek($this->stream, abs($propmlen)); // Properties map length (4, max length 4GB)
        unset($propmlen);

        $cmeta = Utils::readAndSeek($this->stream, 6); // Chunk meta
        // Chunk hash (4), blocks count divided by two / chunk length divided by two (unsigned 2)
        if (strlen($cmeta) !== 8) $this->panicParse("File has no chunks");
        while (Binary::readLInt(substr($cmeta, 0, 4)) !== Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())) {
            fseek($this->stream, Binary::readLShort(substr($cmeta, 4, 2)) * 2);
            $cmeta = Utils::readAndSeek($this->stream, 6);
            if (strlen($cmeta) !== 6) return; // File ends
        }
        $cdata = Utils::readAndSeek($this->stream, $clen = Binary::readLShort(substr($cmeta, 4, 2)) * 2);
        if (strlen($cdata) !== $clen) $this->panicParse("Declared chunk length (" . $clen . ") mismatch with the actual one (file ends after a data of " . strlen($cdata) . " bytes)");
        unset($clen);

        for ($cpointer = 0; $cpointer < $clen * 2; $clen += 2) {
        }
    }

    /**
     * @param string $err
     * @param bool $corrupted
     * @param bool $higherver
     * @throws StructureParseException
     */
    protected function panicParse(string $err, bool $corrupted = true, bool $higherver = false) : void {
        if ($corrupted) $err .= ", is the structure data file occupied?";
        if ($higherver) $err .= ", is the structure exported from a higher version of " . IslandArchitect::PLUGIN_NAME . "?";
        throw new StructureParseException($this->stream, "", $err);
    }

    public static function validateFormatVersion($stream) : bool {
        $ver = Utils::readAndSeek($stream, 1);
        fseek($stream, 1, SEEK_CUR);
        $ver = Binary::readByte($ver);
        return $ver <= self::FORMAT_VERSION;
        // if ($ver > self::FORMAT_VERSION) $this->panicParse("Unsupported structure format version " . $ver . ", try updating " . IslandArchitect::PLUGIN_NAME, false);
    }

}