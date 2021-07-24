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
use pocketmine\math\Vector3;
use pocketmine\utils\Binary;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\ShortTag;
use Clouria\IslandArchitect\Utils;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat as TF;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\structure\StructureData;
use function asort;
use function count;
use function assert;
use function is_int;
use function explode;
use function array_values;
use const SORT_NUMERIC;

class RandomGeneration implements StructureAttachment {

    /**
     * @var array<string, int>
     */
    protected $elements = [];
    /**
     * @var bool
     */
    protected $changed = false;

    public static function fromNBT(ListTag $nbt) : self {
        $self = new self;
        foreach ($nbt as $block) if ($block instanceof CompoundTag) $self->setElementChance($block->getShort('id'), $block->getByte('meta', 0), $block->getShort('chance'));
        return $self;
    }

    /**
     * @param int $id
     * @param int $meta
     * @param int|null $chance
     * @return bool Return false when the chance is slower than 0 or higher than 32767
     */
    public function setElementChance(int $id, int $meta = 0, ?int $chance = null) : bool {
        if ($chance > 32767) return false;
        if ($chance > 0) $this->elements[$id . ':' . $meta] = $chance;
        elseif (isset($this->elements[$id . ':' . $meta])) unset($this->elements[$id . ':' . $meta]);
        $this->changed = true;
        return true;
    }

    public function getElementChance(int $id, int $meta = 0) : int {
        return $this->getAllElements()[$id . ':' . $meta] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function getAllElements() : array {
        return $this->elements;
    }

    public function randomElementItem(Random $random) : Item {
        $array = $this->randomElementArray($random);
        return Item::get($array[0], $array[1]);
    }

    /**
     * @param Random $random
     * @return int[]
     */
    public function randomElementArray(Random $random) : array {
        $blocks = $this->getAllElements();

        // Random crap "proportional random algorithm" code copied from my old plugin
        $upperl = -1;
        foreach ($blocks as $chance) $upperl += $chance;
        if ($upperl < 0) return [Item::AIR, 0];
        $rand = $random->nextRange(0, $upperl);

        $upperl = -1;
        foreach ($blocks as $block => $chance) {
            $upperl += $chance;
            if (($upperl >= $rand) and ($upperl < ($rand + $chance))) {
                $block = explode(':', $block);
                return [(int)($block[0]) & 0xff, (int)($block[1]) & 0xff];
            }
        }
        return [Item::AIR, 0];
    }

    public function equals(RandomGeneration $regex) : bool {
        $blocks = $regex->getAllElements();
        foreach ($this->getAllElements() as $block => $chance) {
            if (($blocks[$block] ?? null) !== $chance) return false;
            unset($blocks[$block]);
        }
        if (!empty($blocks)) return false;
        return true;
    }

    public function getRandomGenerationItem(Item $item, ?int $regexid = null) : Item {
        $totalchance = $this->getTotalChance();
        foreach ($this->getAllElements() as $block => $chance) {
            $block = explode(':', $block);
            $regex[] = new CompoundTag('', [
                new ShortTag('id', (int)$block[0]),
                new ByteTag('meta', (int)($block[1] ?? 0)),
                new ShortTag('chance', $chance),
            ]);
            $bi = Item::get((int)$block[0], (int)($block[1] ?? 0));
            $blockslore[] = $bi->getName() . ' (' . $bi->getId() . ':' . $bi->getDamage() . '): ' . TF::BOLD . TF::GREEN . $chance . TF::ITALIC . ' (' . round($chance / ($totalchance ?? $chance) * 100, 2) . '%%)';
        }
        $i = $item;
        $i->setCustomName(TF::RESET . TF::BOLD . TF::GOLD . 'Random generation' . (!empty($blockslore ?? []) ? ($glue = "\n" . TF::RESET . '- ' . TF::YELLOW) . implode($glue, $blockslore ?? []) : ''));
        $tag = new CompoundTag('IslandArchitect', [
            new CompoundTag('random-generation', [
                new ListTag('regex', $regex ?? []),
            ]),
        ]);
        if (isset($regexid)) $tag->setInt('regexid', $regexid);
        $i->setNamedTagEntry($tag);
        return $i;
    }

    public function getTotalChance() : int {
        $totalchance = 0;
        foreach ($this->getAllElements() as $chance) $totalchance += $chance;
        return $totalchance;
    }

    public function isValid() : bool {
        return !empty($this->getAllElements());
    }

    public function noMoreChanges() : void {
        $this->changed = false;
    }

    public function hasChanges() : bool {
        return $this->changed;
    }

    /**
     * @return bool true = Element chance changed
     */
    public function simplifyRegex() : bool {
        // Forgive my bad math (SOFe is gonna extend his tutorial class...)
        // Source: https://blog.csdn.net/qq_33160365/article/details/78932232
        $totalchance = $this->getTotalChance();
        foreach ($this->getAllElements() as $chance) {
            $smaller = $chance > $totalchance ? $totalchance : $chance;
            for ($i = 1; $i <= $smaller; $i++) if (($chance % $i) === 0 and ($totalchance % $i) === 0) $hcf = $i;
            $chances[] = $hcf ?? 1;
        }
        if (!isset($chances)) return false;
        asort($chances, SORT_NUMERIC);
        $chances = array_values($chances);
        foreach ($this->getAllElements() as $block => $chance) {
            $elements[$block] = $chance / $chances[0];
            if ($elements[$block] !== $chance) $changed = true;
        }
        $this->elements = $elements ?? null;
        if ($changed ?? false) $this->changed = true;
        return $changed ?? false;
    }

    /**
     * @var null|int[]
     */
    protected $cachedElementsMap = null;

    public function run(StructureData $data, int $start, int $length, Vector3 $pos, int $repeat) : Item {
        for (; $repeat >= 0; $repeat--) {
            $stream = $data->getStream();
            if (!isset($this->cachedElementsMap)) {
                $ecount = Binary::readLShort(Utils::readAndSeek($stream, 2)); // Elements count / elements map length divided by two (unsigned )
                $this->cachedElementsMap = [];
                for ($epointer = 0; $epointer < $ecount; $ecount++) $this->cachedElementsMap[] = Binary::readLShort(Utils::readAndSeek($stream, 2)); // Element (unsigned 2)
                unset($ecount, $epointer);
            }
            if (count($this->cachedElementsMap) < 2) throw new \RuntimeException('Random generation regex has less than 2 elements');

            $totalchance = 0;
            foreach ($this->cachedElementsMap as $element) $totalchance += ($element % 16) + 1;

            $rand = $data->getRandom()->nextBoundedInt($totalchance) + 1;

            foreach ($this->cachedElementsMap as $element) {
                $rand -= ($element % 16) + 1;
                if ($rand <= 0) break;
            }
            assert(isset($element) and is_int($element));
            $element = $element >> 4;
            return Item::get($element >> 4, $element % 16); // Add randomized count if possible
            // TODO: Handle NBT
        }
    }

    public static function getIdentifier() : string {
        return IslandArchitect::PLUGIN_NAME . ':random_generation';
    }

    public static function newUnloaded(string $identifier) : StructureAttachment {
        return new self;
    }

    public function isLoaded() : bool {
        return !empty($this->getAllElements());
    }
}