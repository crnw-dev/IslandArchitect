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
use pocketmine\tile\Chest;
use pocketmine\level\Level;
use pocketmine\utils\Random;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat as TF;
use pocketmine\level\format\Chunk;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use Clouria\IslandArchitect\IslandArchitect;
use function explode;
use function filesize;
use function file_exists;
use function is_callable;
use function array_shift;
use function file_get_contents;

class StructureGeneratorTask extends AsyncTask {

    public const SUCCEED = 0;
    public const FILE_NOT_FOUND = 1;
    public const NOT_ENOUGH_MEMORY = 2;
    public const CORRUPTED_FILE = 3;

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
    /**
     * @var Chunk|null
     */
    protected $chunk;
    /**
     * @var string
     */
    protected $type;
    /**
     * @var string
     */
    private $origin;

    public function __construct(string $file, Random $random, Level $level, int $chunkX, int $chunkZ, string $origin = null, ?\Closure $callback = null) {
        $this->storeLocal([$file, $level, $chunkX, $chunkZ, $callback, $origin]);
        $this->file = $file;
        $this->random = $random;
        $this->chunkX = $chunkX;
        $this->chunkZ = $chunkZ;
        $this->origin = $origin;
        $this->chunk = $level->getChunk($chunkX, $chunkZ, true)->fastSerialize();
    }

    public function onRun() {
        $file = $this->file;
        if (!file_exists($file)) {
            $origin = $this->origin;
            if (isset($origin) and file_exists($origin)) copy($origin, $file);
            if (!file_exists($file)) {
                $this->setResult([self::FILE_NOT_FOUND]);
                return;
            }
        }
        $reflect = new \ReflectionProperty($this->worker, 'memoryLimit');
        $reflect->setAccessible(true);
        $memlimit = $reflect->getValue($this->worker);
        $memlimit = $memlimit * 1024 * 1024;
        if (filesize($file) > $memlimit) {
            $this->setResult([self::NOT_ENOUGH_MEMORY]);
            return;
        }
        for ($t = 0; $t <= 3; $t++) { // TODO: Make the timeout customizable
            $struct = TemplateIsland::load(file_get_contents($file));
            if ($struct !== null) break;
            sleep(2);
        }
        if (!isset($struct)) {
            $this->setResult([self::CORRUPTED_FILE]);
            return;
        }

        $cx = $this->chunkX;
        $cz = $this->chunkZ;
        $chunk = Chunk::fastDeserialize($this->chunk);

        $random = $this->random;
        $blocks = 0;
        for ($y = 0; $y <= Level::Y_MAX; $y++) {
            $subchunk = $chunk->getSubChunk($y >> 4, true);
            for ($x = $cx << 4; $x < ($cx + 1) << 4; $x++)
                for ($z = $cz << 4; $z < ($cz + 1) << 4; $z++) {
                    $block = $struct->getProcessedBlock($x, $y, $z, $random);
                    if (!isset($block)) continue;
                    if ($subchunk->setBlock($x & 0x0f, $y & 0x0f, $z & 0x0f, $block[0], $block[1])) $blocks++;
                }
        }

        foreach ($struct->getChests() as $coordraw => $chest) {
            $coord = explode(':', $coordraw);
            if (((int)$coord[0] >> 4) !== $cx or ((int)$coord[2] >> 4) !== $cz) continue;
            $chests[$coordraw] = $chest->getRuntimeContents($random);
        }

        $this->setResult([self::SUCCEED, $blocks > 0 ? $chunk : null, $random, $struct->getSpawn(), $struct->getYOffset(), $chests ?? []]);
    }

    public function onCompletion(Server $server) : void {
        $fridge = $this->fetchLocal();
        $result = $this->getResult();
        $level = $fridge[1];
        if ($result[0] !== self::SUCCEED) {
            $reason = [
                          self::FILE_NOT_FOUND => 'Structure data not found',
                          self::NOT_ENOUGH_MEMORY => 'Generator worker thread ran out of memory',
                          self::CORRUPTED_FILE => 'Corrupted file',
                      ][$result[0]];
            if ($level instanceof Level) {
                foreach ($level->getPlayers() as $p) $p->sendMessage(TF::BOLD . TF::RED . 'Critical error occurred while generating your island: ' . $reason . '. Consider asking a server admin for help!');
                $details[] = 'Level: ' . $level->getProvider()->getName();
                $details[] = 'Players in level: ' . implode(', ', $level->getPlayers());
            }
            $details[] = 'Expected structure file path: ' . $fridge[0];
            $details[] = 'Source structure file path: ' . $fridge[5];
            $details[] = 'Chunk: ' . $fridge[2] . ', ' . $fridge[3];
            IslandArchitect::getInstance()->getLogger()->error(TF::BOLD . TF::RED . 'Critical error occurred while generating structure:' . implode("\n", $details ?? []));
        }
        if ($level instanceof Level) {
            $chunk = $result[1];
            $cx = (int)$fridge[2];
            $cz = (int)$fridge[3];
            if ($chunk instanceof Chunk) IslandArchitect::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $ct) use ($result, $cz, $cx, $chunk, $level) : void {
                $level->setChunk($cx, $cz, $chunk);
                foreach ($result[5] as $coord => $chest) {
                    $coord = explode(':', $coord);
                    $tile = Chest::createTile(Chest::CHEST, $level, Chest::createNBT(new Vector3((int)$coord[0], (int)$coord[1], (int)$coord[2])));
                    if ($tile instanceof Chest) {
                        $inv = $tile->getInventory();
                        $inv->setContents($chest);
                    }
                }
            }), 20);
            $spawn = $result[3];
            if ($spawn instanceof Vector3) $spawn = $spawn->add(0, $result[4]);
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
