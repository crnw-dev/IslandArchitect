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

use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\utils\Binary;
use pocketmine\utils\Random;
use Clouria\IslandArchitect\Utils;
use pocketmine\level\format\Chunk;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\properties\StructureProperty;
use function fseek;
use function substr;
use function strlen;
use const SEEK_CUR;

class StructureData {

    public const FORMAT_VERSION = 0;
    public const Y_MAX = 0x100; // Hardcode Y max or breaks block filling after Y is has increased
    public const BKV_TYPE_SIZE = 16384;

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
     * Please notice that every bytes are (should) stored in the order of little endian. If you found any that are not, please consider open a pull request / issue to let me know
     * @throws StructureParseException
     */
    public function decode() {
        fseek($this->stream, 2);

        $propcount = Utils::readAndSeek($this->stream, 2); // Properties count (unsigned 2)
        if (strlen($propcount) !== 2) $this->panicParse("File ends with no chunks or properties");
        for ($proppointer = 0; $proppointer < $propcount; $proppointer++) fseek($this->stream, Binary::readLShort(Utils::readAndSeek($this->stream, 2)), SEEK_CUR); // Property length (unsigned 2), max size 64KB per property

        $cmeta = Utils::readAndSeek($this->stream, 6); // Chunk meta
        // Chunk hash (4), chunk length divided by two / blocks count (unsigned 2)
        if (strlen($cmeta) !== 8) $this->panicParse("File has no chunks");
        while (Binary::readLInt(substr($cmeta, 0, 4)) !== Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())) {
            fseek($this->stream, Binary::readLShort(substr($cmeta, 4, 2)) * 2);
            $cmeta = Utils::readAndSeek($this->stream, 6);
            if (strlen($cmeta) !== 6) return; // File ends
        }
        $cdata = Utils::readAndSeek($this->stream, $clen = Binary::readLShort(substr($cmeta, 4, 2)) * 2);
        /*
         * Chunk data are basically just blocks, each block are stored in unsigned shorts (2)
         * The value is split into 4 types, each type has the size of 16384
         */
        if (strlen($cdata) !== $clen) $this->panicParse("Declared chunk length (" . $clen . ") mismatch with the actual one (file ends after a data of " . strlen($cdata) . " bytes)");

        $clocator = 0;
        for ($cpointer = 0; $cpointer < $clen * 2; $cpointer += 2) {
            $bkraw = Binary::readLShort(substr($cdata, $cpointer, 2));
            switch ($bktype = ceil(($bkraw + 1 / self::BKV_TYPE_SIZE))) {
                case 1: // Regular block
                    $bk = $bkraw % self::BKV_TYPE_SIZE;
                    $id = $bk >> 4;
                    if ($id !== Item::AIR) $this->chunk->setBlock($clocator % 16, $clocator >> 4 >> 4 % self::Y_MAX, ($clocator >> 4) % 16, $id, $bk % 16);
                    break;
                case 2: // Block skipping (air)
                    $clocator += ($bktype % self::BKV_TYPE_SIZE) + 1;
                    break;

                case 3:
                    break;

                // Type 4 will not be handled as they are not used in this version of IslandArchitect

            }
            $clocator++;
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