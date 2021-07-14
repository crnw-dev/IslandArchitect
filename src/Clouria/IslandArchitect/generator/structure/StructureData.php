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
use pocketmine\utils\Random;
use Clouria\IslandArchitect\Utils;
use pocketmine\level\format\Chunk;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\properties\StructureProperty;
use function fseek;
use function substr;
use function strlen;
use function is_resource;
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
        if (!is_resource($this->stream)) return;
        $props = Utils::readAndSeek($this->stream, 4); // Properties map, max length 1GB
        // Map length (unsigned 4)
        $cmeta = Utils::readAndSeek($this->stream, 8); // Chunk meta
        // Chunk hash (signed 4), chunk length divided by two (signed 4)

        do {
            $junction = Utils::readAndSeek($this->stream, 5);
            // Part expand type (1), signed part length (2), signed part trailing offset (2)
            $ptype = Binary::readByte($junction[0]);

            if ($ptype > 64) break; // End of chunks, structure properties are the next

            $plen = Utils::overflowBytes(substr($junction, 1, 2));
            $ptrailing = Utils::overflowBytes(substr($junction, 3, 2));
            unset($junction);

            $clen -= 3 + $plen;

            $pdata = Utils::readAndSeek($this->stream, $plen);
            for ($bkpointer = 0; $bkpointer < strlen($pdata); $bkpointer += 2) {
                $bklocator = $ptrailing + $bkpointer / 2;
                // Part trailing offset are basically the count of air blocks at the front of part data
                $bk = Utils::overflowBytes(substr($pdata, $bkpointer, 2));

                $id = ($bk + 1) / 16;
                $meta = $bk % 16;
                unset($bk);
                $f = ($bklocator) % self::Y_MAX;
                $s = (int)($bklocator / self::Y_MAX) % 16;
                $t = (int)((int)($bklocator / self::Y_MAX) / 16);
                if ($ptype % 4 > 1) {
                    $y = $t;
                    if ($ptype % 2) {
                        $x = $f;
                        $z = $s;
                    } else {
                        $x = $s;
                        $z = $f;
                    }
                } else {
                    $y = $f;
                    if ($ptype % 2) {
                        $x = $s;
                        $z = $t;
                    } else {
                        $x = $t;
                        $z = $s;
                    }
                }
                if ($ptype % 8 > 3) $x = 15 - $x;
                if ($ptype % 8 > 3) $z = 15 - $z;
                if ($ptype % 16 > 7) $y = self::Y_MAX - 1 - $y;
                $this->chunk->setBlock($x, $y, $z, $id, $meta);
            }
        } while ($clen > 0);
        unset($ptype, $ptrailing, $bklocator, $bkpointer, $pdata, $plen);

        if (!isset($junction)) $junction = Utils::readAndSeek($this->stream, 1);
        // Structure property identifier length (1), structure property data length (4)
        $pnamelen = Binary::readByte($junction[0]);
        $pdatalen = Utils::overflowBytes(substr($junction, 1, 4));
        unset($junction);
        $pname = Utils::readAndSeek($this->stream, $pnamelen);
        if (!isset($this->properties[$pname])) $this->panicParse("Structure property \"" . $pname . "\" not found", false, true);

        $this->properties[$pname]->getFunc()($this, $pdatalen);
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