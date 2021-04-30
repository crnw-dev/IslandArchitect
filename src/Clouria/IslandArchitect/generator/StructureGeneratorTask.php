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

use pocketmine\Server;
use pocketmine\level\Level;
use pocketmine\utils\Random;
use pocketmine\level\format\Chunk;
use pocketmine\scheduler\AsyncTask;
use function filesize;
use function file_exists;
use function file_get_contents;

class StructureGeneratorTask extends AsyncTask {

    protected $file;
    /**
     * @var int
     */
    protected $chunkX;
    /**
     * @var int
     */
    protected $chunkZ;
    /**
     * @var Random
     */
    protected $random;

    public function __construct(string $file, Random $random, Level $level, int $chunkX, int $chunkZ, ?\Closure $callback = null) {
        $this->storeLocal([$file, $level, $chunkX, $chunkZ, $callback]);
        $this->file = $file;
        $this->random = $random;
        $this->chunkX = $chunkX;
        $this->chunkZ = $chunkZ;
    }

    public function onRun() {
        $file = $this->file;
        if (!file_exists($file)) {
            $this->setResult(null);
            return;
        }
        $reflect = new \ReflectionProperty($this->worker, 'memoryLimit');
        $reflect->setAccessible(true);
        $memlimit = $reflect->getValue($this->worker);
        if (filesize($file) > $memlimit) {
            $this->setResult(null);
            return;
        }
        $struct = TemplateIsland::load(file_get_contents($file));
        $chunk = new Chunk($this->chunkX, $this->chunkZ);
        $chunk->setGenerated(true);
        $random = $this->random;
        for ($x = $this->chunkX << 4; $x < ($this->chunkX + 1) << 4; $x++)
            for ($z = $this->chunkZ << 4; $z < ($this->chunkZ + 1) << 4; $z++)
                for ($y = 0; $y <= Level::Y_MAX; $y++) {
                    $block = $struct->getProcessedBlock($x, $y, $z, $random);
                    $chunk->setBlock($x, $y, $z, $block[0], $block[1]);
                }
        $this->setResult([$chunk, $random]);
    }

    public function onCompletion(Server $server) : void {
        $fridge = $this->fetchLocal();
        $result = $this->getResult();
        $level = $fridge[1];
        if ($level instanceof Level) $level->setChunk((int)$fridge[2], (int)$fridge[3], $result[0]);
    }
}
