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
use pocketmine\math\Vector3;
use pocketmine\level\format\Chunk;
use pocketmine\scheduler\AsyncTask;
use Clouria\IslandArchitect\IslandArchitect;
use function filesize;
use function var_dump;
use function file_exists;
use function is_callable;
use function array_shift;
use function file_get_contents;

class StructureGeneratorTask extends AsyncTask {

    public const SUCCEED = 0;
    public const FILE_NOT_FOUND = 1;
    public const NOT_ENOUGH_MEMORY = 2;

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
            $this->setResult([self::FILE_NOT_FOUND]);
            return;
        }
        $reflect = new \ReflectionProperty($this->worker, 'memoryLimit');
        $reflect->setAccessible(true);
        $memlimit = $reflect->getValue($this->worker);
        $memlimit = $memlimit * 1024 * 1024;
        if (filesize($file) > $memlimit) {
            $this->setResult([self::NOT_ENOUGH_MEMORY]);
            return;
        }
        $struct = TemplateIsland::load(file_get_contents($file));
        $chunk = new Chunk($this->chunkX, $this->chunkZ);
        $chunk->setGenerated(true);

        $cx = $this->chunkX;
        $cz = $this->chunkZ;
        var_dump('chunk: ' . $cx . ':' . $cz);
        $random = $this->random;
        for ($x = $cx << 4; $x < ($cx + 1) << 4; $x++)
            for ($z = $cz << 4; $z < ($cz + 1) << 4; $z++)
                for ($y = 0; $y <= Level::Y_MAX; $y++) {
                    $block = $struct->getProcessedBlock($x, $y, $z, $random);
                    if ($this->chunkX === 1 and $this->chunkZ === 0) var_dump($x . ':' . $y . ':' . $z, $block);
                    if (!isset($block)) continue;
                    $chunk->setBlock($x, $y, $z, $block[0], $block[1]);
                }
        $this->setResult([self::SUCCEED, $chunk, $random, $struct->getSpawn()]);
    }

    public function onCompletion(Server $server) : void {
        // TODO: Handle error if failed to generate structure
        $fridge = $this->fetchLocal();
        $result = $this->getResult();
        $log = IslandArchitect::getInstance()->getLogger();
        switch ($result[0]) {

            case self::FILE_NOT_FOUND:
                $log->critical('File not found');
                return;

            case self::NOT_ENOUGH_MEMORY:
                $log->critical('Not enough memory');
                return;
        }
        $level = $fridge[1];
        if ($level instanceof Level) {
            var_dump('done');
            $level->setChunk((int)$fridge[2], (int)$fridge[3], $result[1]);
            $spawn = $result[3];
            if ($spawn instanceof Vector3 and !$spawn->equals($level->getSpawnLocation())) {
                $level->setSpawnLocation($spawn);
                foreach ($level->getPlayers() as $p) $p->teleport($level->getSpawnLocation());
            }
        }
        $callback = $fridge[4];
        array_shift($result);
        if (is_callable($callback)) $callback(...$result);
    }
}
