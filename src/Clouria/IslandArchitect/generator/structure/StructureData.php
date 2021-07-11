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
use pocketmine\level\format\Chunk;
use Clouria\IslandArchitect\IslandArchitect;
use function abs;
use function fseek;
use function substr;
use function strlen;
use function is_resource;
use const SEEK_CUR;
use const PHP_INT_MAX;

class StructureData {

    public const FORMAT_VERSION = 0;
    public const CHUNKMAP_ELEMENT_SIZE = 10;
    public const Y_MAX = 0x100; // Hardcode Y max or breaks block filling after Y is has increased

    /**
     * @var resource
     */
    public $stream;

    /**
     * @var Chunk
     */
    public $chunk;

    /**
     * @throws StructureParseException
     */
    public function decode() {
        if (!is_resource($this->stream)) return;
        fseek($this->stream, 0);
        $header = Utils::ReadAndSeek($this->stream, 4);
        // Format version (1), sub version (1), chunks count (2)

        $ver = Binary::readByte($header[0]);
        if ($ver > self::FORMAT_VERSION) $this->panicParse("Unsupported structure format version " . $ver, false, false);

        $ccount = Binary::readSignedLShort(substr($header, 2));
        $cmap = Utils::ReadAndSeek($this->stream, self::CHUNKMAP_ELEMENT_SIZE * $ccount);
        // An array (32) of chunk hash (2) followed by chunk length (8), max limit 2MB per chunk
        unset($header, $ver);

        for ($cpointer = 0; $cpointer < $ccount * self::CHUNKMAP_ELEMENT_SIZE; $cpointer += self::CHUNKMAP_ELEMENT_SIZE) {
            $cmapped = substr($cmap, $cpointer, self::CHUNKMAP_ELEMENT_SIZE);
            $mappedlen = Binary::readLLong(substr($cmapped, 2));
            $mappedhash = Binary::readSignedLShort(substr($cmapped, 0, 2));
            if ($mappedhash < 0) $mappedhash += 2147483647 - $mappedhash - 1;
            if ($mappedhash === Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())) {
                $clen = $mappedlen;
                break;
            }

            if ($mappedlen < 0) fseek($this->stream, PHP_INT_MAX, SEEK_CUR);
            fseek($this->stream, (int)(abs($mappedlen) - ($mappedlen < 0 ? 1 : 0)), SEEK_CUR);
        }
        unset($cmap, $cpointer, $ccount, $cmapped, $mappedlen, $mappedhash);
        if (!isset($clen)) return;

        do {
            $junction = Utils::ReadAndSeek($this->stream, 5);
            // Part expand type (1), signed part length (2), signed part trailing offset (2)
            $ptype = Binary::readByte($junction[0]);
            $plen = Binary::readSignedLShort(substr($junction, 1, 2));
            if ($plen < 0) $plen += 32767 - $plen - 1;
            $ptrailing = Binary::readSignedByte(substr($junction, 3, 2));
            if ($ptrailing < 0) $ptrailing += 32767 - $ptrailing - 1;
            unset($junction);

            $clen -= 3 + $plen;

            $pdata = Utils::ReadAndSeek($this->stream, $plen);
            for ($bkpointer = 0; $bkpointer < strlen($pdata); $bkpointer += 2) {
                $bklocator = $ptrailing + $bkpointer / 2;
                // Part trailing offset are basically the count of air blocks at the front of part data
                $bk = Binary::readSignedLShort(substr($pdata, $bkpointer, 2));
                if ($bk < 0) $bk += 32767 - $bk - 1;

                $id = ($bk + 1) / 16;
                $meta = $bk % 16;
                switch ($ptype % 2) {
                    case 1:
                        $x = (int)((int)($bklocator / self::Y_MAX) / 16);
                        $y = ($bklocator) % self::Y_MAX;
                        $z = (int)($bklocator / self::Y_MAX) % 16;
                        break;

                    default:
                        $x = (int)($bklocator / self::Y_MAX) % 16;
                        $y = ($bklocator) % self::Y_MAX;
                        $z = (int)((int)($bklocator / self::Y_MAX) / 16);
                        break;
                }
                if ($ptype % 4 === 2) $x = 15 - $x;
                if ($ptype % 4 === 3) $z = 15 - $z;
                if ($ptype >= 4) $y = self::Y_MAX - 1 - $y;
                $this->chunk->setBlock($x, $y, $z, $id, $meta);
            }
        } while ($clen > 0);
    }

    /**
     * @param bool $higherver
     * @throws StructureParseException
     */
    protected function panicParse(string $err, bool $corrupted = true, bool $higherver = false) : void {
        if ($corrupted) $err .= ", is the structure data file occupied?";
        if ($higherver) $err .= ", is the structure exported from a higher version of " . IslandArchitect::PLUGIN_NAME . "?";
        throw new StructureParseException($this->stream, "", $err);
    }

}