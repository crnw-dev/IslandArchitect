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
														*/
declare(strict_types=1);

namespace Clouria\IslandArchitect\generator\properties;

use function is_array;

class IslandChest {

    /**
     * @var \SplFixedArray<string|RandomGeneration>
     */
    protected $contents;

    public function __construct(array $contents = []) {
        $this->contents = new \SplFixedArray($size = 27);
        if (count($contents) > $size) throw new \InvalidArgumentException('More than ' . $size . ' items want to fit in a ' . $size . ' slots (0-' . ($size - 1) . ') container');
        foreach ($contents as $content) {
            if (is_array($content)) $content = $content[0] . ':' . $content[1] ?? 0;
            // TODO: Allow random generation regex to be put into island chests
            $this->contents[] = $content;
        }
    }

    /**
     * @return array<string|RandomGeneration>
     */
    public function getContents() : array {
        foreach ($this->contents as $index => $content) $contents[$index] = $content;
        return $contents ?? [];
    }

    public function setItem(int $slot, int $id, int $meta = 0) : bool {
        if ($slot >= $this->contents->getSize()) return false;
        $this->contents[$slot] = $id . ':' . $meta;
        return true;
    }

    public function setRandom(int $slot, RandomGeneration $regex) : bool {
        if ($slot >= $this->contents->getSize()) return false;
        $this->contents[$slot] = $regex;
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
        return true;
    }
}