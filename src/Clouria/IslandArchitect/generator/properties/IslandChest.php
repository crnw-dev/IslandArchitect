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

namespace Clouria\IslandArchitect\generator\properties;

use pocketmine\item\Item;
use pocketmine\utils\Random;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\BigEndianNBTStream;
use function is_array;
use function is_string;
use function base64_encode;

class IslandChest {

    /**
     * @var \SplFixedArray<string|RandomGeneration>
     */
    protected $contents;
    /**
     * @var false
     */
    protected $changed = false;

    public function __construct(array $contents = []) {
        $this->contents = new \SplFixedArray($size = 27);
        if (!$this->setContents($contents)) throw new \InvalidArgumentException('More than ' . $size . ' items want to fit in a ' . $size . ' slots (0-' . ($size - 1) . ') container');
    }

    /**
     * @return array<string|RandomGeneration>
     */
    public function getContents() : array {
        foreach ($this->contents as $index => $content) $contents[$index] = $content;
        return $contents ?? [];
    }

    public function setItem(int $slot, int $id, int $meta = 0, int $count = 64, ?CompoundTag $nbt = null) : bool {
        if ($slot >= $this->contents->getSize()) return false;
        if (isset($nbt) and count($nbt) > 0) {
            $stream = new BigEndianNBTStream;
            $stream->writeTag($nbt);
            $stream = $stream->get(true);
            $stream = base64_encode($stream);
        }
        $this->contents[$slot] = $id . ':' . $meta . ':' . $count . (isset($stream) ? ':' . $stream : '');
        $this->changed = true;
        return true;
    }

    public function setRandom(int $slot, RandomGeneration $regex) : bool {
        if ($slot >= $this->contents->getSize()) return false;
        $this->contents[$slot] = $regex;
        $this->changed = true;
        return true;
    }

    /**
     * @param int $slot
     * @return string|RandomGeneration|null
     */
    public function getContentAt(int $slot) {
        return $this->contents[$slot] ?? null;
    }

    public function removeContentAt(int $slot) : bool {
        if (!isset($this->contents[$slot])) return false;
        unset($this->contents[$slot]);
        $this->changed = true;
        return true;
    }

    public function setContents(array $contents) : bool {
        if (count($contents) > $this->contents->getSize()) return false;
        foreach ($contents as $slot => $content) {
            if (is_array($content)) $content = $content[0] . ':' . $content[1] ?? 0;
            $this->contents[$slot] = $content;
        }
        return true;
    }

    public function noMoreChanges() : void {
        $this->changed = false;
    }

    public function hasChanges() : bool {
        return $this->changed;
    }

    /**
     * @return Item[]
     */
    public function getRuntimeContents(Random $random) : array {
        foreach ($this->contents as $content) {
            if (is_string($content)) {
                $content = explode(':', $content);
                if (isset($content[3])) {
                    $stream = new BigEndianNBTStream;
                    $stream = $stream->read((string)$content[3]);
                    if ($stream instanceof CompoundTag and $stream->getName() === '') $nbt = $stream;
                }
                $contents[] = Item::get((int)$content[0], (int)$content[1], (int)$content[2], $nbt ?? '');
            }
            if ($content instanceof RandomGeneration) $contents[] = $content->randomElementItem($random);
        }
        return $contents ?? [];
    }
}