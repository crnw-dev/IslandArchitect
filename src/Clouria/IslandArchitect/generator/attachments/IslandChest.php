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

use pocketmine\item\Item;
use pocketmine\utils\Random;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\LittleEndianNBTStream;
use function is_string;
use function base64_decode;
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
        if (!$this->setContents($contents)) throw new \InvalidArgumentException('More than ' . $size . ' items attempt to fit into a ' . $size . ' slots (0-' . ($size - 1) . ') container');
    }

    /**
     * @return array<string|RandomGeneration>
     */
    public function getContents() : array {
        foreach ($this->contents as $index => $content) if (isset($contents)) $contents[$index] = $content;
        return $contents ?? [];
    }

    public function setItem(int $slot, int $id, int $meta = 0, int $count = 64, ?CompoundTag $nbt = null) : bool {
        if ($slot >= $this->contents->getSize()) return false;
        if ($id === Item::AIR) return true;
        $this->contents[$slot] = '0:' . $id . ':' . $meta . ':' . $count;
        if (isset($nbt) and count($nbt) > 0) $this->contents[$slot] .= ':' . base64_encode((new LittleEndianNBTStream)->write($nbt));
        $this->changed = true;
        return true;
    }

    public function setRandom(int $slot, int $regexid) : bool {
        if ($slot >= $this->contents->getSize()) return false;
        $this->contents[$slot] = '1:' . $regexid;
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

    /**
     * @param string[] $contents Contents in the array won't be validate, only checks whether it is a string or not!
     * @return bool
     */
    public function setContents(array $contents) : bool {
        for ($slot = 0; $slot < $this->contents->getSize(); $slot++) unset($this->contents[$slot]);
        if (count($contents) > $this->contents->getSize()) return false;
        foreach ($contents as $slot => $content) if (is_string($content)) $this->contents[(int)$slot] = $content;
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
        foreach ($this->contents as $slot => $content) {
            if ($content === null) continue;
            if (is_string($content)) {
                $content = explode(':', $content);
                try {
                    $contents[] = Item::get((int)$content[1], (int)$content[2], (int)$content[3], isset($content[4]) ? base64_decode((string)$content[4]) : '');
                } catch (\InvalidArgumentException $err) {
                    continue;
                }
            }
            if ($content instanceof RandomGeneration) $contents[$slot] = $content->randomElementItem($random);
        }
        return $contents ?? [];
    }
}