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

namespace Clouria\IslandArchitect\generator;

use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use room17\SkyBlock\island\generator\IslandGenerator;
use Clouria\IslandArchitect\extended\skyblock\CustomSkyBlockCreateCommand;
use function implode;
use function is_array;
use function is_string;
use function unserialize;
use function file_exists;
use function file_get_contents;

class TemplateIslandGenerator extends IslandGenerator {

    public const GENERATOR_NAME = 'templateislandgenerator';

    /**
     * @var TemplateIsland|null
     */
    protected $island = null;

    public function __construct(array $settings = []) {
        parent::__construct($settings);

        if (isset($settings['IslandArchitect'])) {
            $data = unserialize($settings['IslandArchitect']);
            if ($data[0] === CustomSkyBlockCreateCommand::ISLAND_DATA_ARRAY and isset($data[1])) $island = TemplateIsland::load($data[1]);
        } else {
            $data = unserialize($this->getSettings()['preset']);
            if (is_string($data[0]) and $data[0][0] === 'O') {
                $serialized = explode(':', $data[0][0]);
                if ($serialized[2] === 'Clouria\IslandArchitect\runtime\TemplateIsland') $serialized[2] = TemplateIsland::class;
                $data[0] = implode(':', $serialized);
                $island = @unserialize($data[0]);
                if (!$island instanceof TemplateIsland) $island = null;
            } elseif (is_array($data[0])) $island = TemplateIsland::load($data[0]);
            else switch ($data[0]) {

                case CustomSkyBlockCreateCommand::ISLAND_DATA_ARRAY:
                    $island = TemplateIsland::loadArray((array)($data[1] ?? []));
                    break;

                case CustomSkyBlockCreateCommand::FILE_PATH:
                    if (isset($data[1]) and file_exists((string)$data[1])) $island = TemplateIsland::load(file_get_contents((string)$data[1]));
                    break;
            }
        }
        if (!isset($island)) throw new \RuntimeException('Cannot pass template island instance into the generator thread');
        $this->island = $island;
    }

    public static function getWorldSpawn() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public static function getChestPosition() : Vector3 {
        return new Vector3(0, 0, 0);
    }

    public function generateChunk(int $chunkX, int $chunkZ) : void {
        $chunk = $this->level->getChunk($chunkX, $chunkZ);
        $chunk->setGenerated();
        for ($x = 0; $x < 16; $x++) for ($z = 0; $z < 16; $z++) for ($y = 0; $y <= Level::Y_MAX; $y++) {
            $block = $this->island->getProcessedBlock(($chunk->getX() << 4) + $x, $y, ($chunk->getZ() << 4) + $z, $this->random);
            if ($block === null or $block[0] === Item::AIR) continue;
            $chunk->setBlock($x, $y, $z, (int)$block[0], (int)$block[1]);
        }
    }

    public function getName() : string {
        return isset($this->island) ? $this->island->getName() : 'TemplateIslandGenerator';
    }

    public function populateChunk(int $chunkX, int $chunkZ) : void { }

    public function getSpawn() : Vector3 {
        return $this->island->getSpawn();
    }
}
