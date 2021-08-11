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
use pocketmine\math\Vector3;
use Clouria\IslandArchitect\Utils;
use pocketmine\level\format\Chunk;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\attachments\IslandChest;
use Clouria\IslandArchitect\generator\attachments\NullAttachment;
use Clouria\IslandArchitect\generator\attachments\RandomGeneration;
use Clouria\IslandArchitect\generator\attachments\StructureAttachment;
use function max;
use function is_a;
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

    protected $attachmentsCount = null;

    /**
     * @var array<string, class-string<StructureAttachment>>
     */
    protected $attachmentClasses = [];

    /**
     * Please notice that every bytes are (should) stored in the order of little endian. If you found any that are not, please consider open a pull request / issue to let me know
     * @throws StructureParseException
     */
    public function decode() {
        fseek($this->stream, 2);
        for ($attachmentPointer = 0; $attachmentPointer < $this->getAttachmentsCount(); $attachmentPointer++) fseek($this->stream, Binary::readLShort(Utils::readAndSeek($this->stream, 2)), SEEK_CUR);
        // Structure attachment length (unsigned 2), max size 64 KiB per structure attachment
        unset($attachmentPointer);

        $cmeta = Utils::readAndSeek($this->stream, 6); // Chunk meta
        // Chunk hash (4), chunk length divided by two / blocks count (unsigned 2)
        if (strlen($cmeta) !== 8) $this->panicParse('File has no chunks');
        while (Binary::readLInt(substr($cmeta, 0, 4)) !== Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())) {
            fseek($this->stream, Binary::readLShort(substr($cmeta, 4, 2)) * 2, SEEK_CUR);
            $cmeta = Utils::readAndSeek($this->stream, 6);
            if (strlen($cmeta) !== 6) return; // File ends
        }
        $cdata = Utils::readAndSeek($this->stream, $clen = Binary::readLShort(substr($cmeta, 4, 2)) * 2);
        /*
         * Chunk data are basically just blocks, each block are stored in unsigned shorts (2)
         * The value is split into 4 types, each type has the size of 16384
         */
        if (strlen($cdata) !== $clen) $this->panicParse('Declared chunk length (' . $clen . ') mismatch with the actual one (file ends after a data of ' . strlen($cdata) . ' bytes)');

        $clocator = $repeat = 0;
        for ($cpointer = 0; $cpointer < $clen * 2; $cpointer += 2) {
            $bkraw = Binary::readLShort(substr($cdata, $cpointer, 2));
            switch ($bktype = ceil(($bkraw + 1 / self::BKV_TYPE_SIZE))) {
                case 1: // Regular block
                    $bk = $bkraw % self::BKV_TYPE_SIZE;
                    $id = $bk >> 4;
                    for (; $clocator < $clocator + max($repeat, 1); $clocator++) if ($id !== Item::AIR) $this->chunk->setBlock($clocator % 16, $clocator >> 4 >> 4 % self::Y_MAX, ($clocator >> 4) % 16, $id, $bk % 16);
                    $repeat = 0;
                    break;
                case 2: // Block skipping (air)
                    $clocator += ($bktype % self::BKV_TYPE_SIZE) + 1;
                    break;

                case 3:
                    $pointer = ftell($this->stream);
                    if ($pointer === false) throw new \RuntimeException('Cannot fetch the position of pointer from file descriptor');
                    $id = $bkraw % self::BKV_TYPE_SIZE;
                    $attach = $this->loadAttachment($id, $start, $attachmentLen);
                    if ($attach === null) throw new \RuntimeException('Structure attachment ' . $id . ' is required but cannot be found in the structure data');
                    $attach->run($this, $start, $attachmentLen, new Vector3($clocator % 16, $clocator >> 4 >> 4 % self::Y_MAX, ($clocator >> 4) % 16), $repeat);
                    fseek($this->stream, $pointer);
                    break;

                case 4:
                    // Block repeating (Doing basically the same thing as type 2 but not just repeating air)
                    // Instead, it repeats the block of the next block value
                    // The next block value should not be 2 or 4
                    $repeat = ($bktype % self::BKV_TYPE_SIZE) + 1;
                    $clocator++;
                    break;

            }
        }
    }

    /**
     * @return Chunk
     */
    public function getChunk() : Chunk {
        return $this->chunk;
    }

    /**
     * @return resource
     */
    public function getStream() {
        return $this->stream;
    }

    /**
     * @return Random
     */
    public function getRandom() : Random {
        return $this->random;
    }

    /**
     * @param string $err
     * @param bool $corrupted
     * @param bool $higherver
     * @throws StructureParseException
     */
    private function panicParse(string $err, bool $corrupted = true, bool $higherver = false) : void {
        if ($corrupted) $err .= ', is the structure data file occupied?';
        if ($higherver) $err .= ', is the structure exported from a higher version of ' . IslandArchitect::PLUGIN_NAME . '?';
        throw new StructureParseException($this->stream, '', $err);
    }

    public static function validateFormatVersion($stream) : bool {
        $ver = Utils::readAndSeek($stream, 1);
        fseek($stream, 1, SEEK_CUR);
        $ver = Binary::readByte($ver);
        return $ver <= self::FORMAT_VERSION;
        // if ($ver > self::FORMAT_VERSION) $this->panicParse('Unsupported structure format version ' . $ver . ', try updating ' . IslandArchitect::PLUGIN_NAME, false);
    }

    /**
     * @var StructureAttachment[]
     */
    protected $attachmentCaches = [];

    /**
     * @param int $id
     * @param $start mixed|int The start offset of attachment will be assign to this pointer
     * @param $length mixed|int The data length (excludes attachment header) of attachment will be assign to this pointer
     * @param bool $cache Get attachment instance from cache pool? / Save it to the cache pool after loading?
     * @return StructureAttachment|null
     * @throws StructureParseException Structure attachments count cannot be loaded
     */
    public function loadAttachment(int $id, &$start, &$length, bool $cache = true) : ?StructureAttachment {
        if ($cache and isset($this->attachmentCaches[$id])) return $this->attachmentCaches[$id];
        if ($this->getAttachmentsCount() <= $id) return null;
        fseek($this->stream, $start = 2);
        for ($pointer = 0; $pointer < $id; $pointer++) {
            $start += Binary::readLShort(Utils::readAndSeek($this->stream, 2));
            fseek($this->stream, $start, SEEK_CUR);
        }

        $start += 3;
        $header = Utils::readAndSeek($this->stream, 3);
        $datalen = Binary::readLShort(substr($header, 0, 2));
        $namelen = Binary::readByte($header[2]);
        $length = $datalen[3] - $namelen;
        switch ($id = Utils::readAndSeek($this->stream, $namelen)) {
            case RandomGeneration::getIdentifier():
                $class = RandomGeneration::class;
                break;
            case IslandChest::getIdentifier():
                $class = IslandChest::class;
                break;
            default:
                $class = $this->attachmentClasses[$id] ?? NullAttachment::class;
                break;
        }
        if (!is_a($class, StructureAttachment::class, true)) throw new \RuntimeException('Class ' . $class . '" does not implement the StructureAttachment interface but got registered as an structure attachment');
        $new = $class::newUnloaded($id);
        if ($cache) $this->attachmentCaches[$id] = $new;
        return $new;
    }

    /**
     * @return int Attempt to load the structure attachments count if null
     * @throws StructureParseException
     */
    public function getAttachmentsCount() : int {
        if ($this->attachmentsCount === null) return $this->loadAttachmentsCount();
        return $this->attachmentsCount;
    }

    /**
     * Please set the stream pointer to 2 before running
     * @throws StructureParseException
     */
    protected function loadAttachmentsCount() : int {
        $attachmentsCount = Utils::readAndSeek($this->stream, 2); // Structure attachments count (unsigned 2)
        if (strlen($attachmentsCount) !== 2) $this->panicParse('File ends with no chunks and attachments');
        $this->attachmentsCount = Binary::readLShort($attachmentsCount);
        return $this->attachmentsCount;
    }

}