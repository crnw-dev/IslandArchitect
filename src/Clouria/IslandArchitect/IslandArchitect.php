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
namespace Clouria\IslandArchitect;


use room17\SkyBlock\SkyBlock;
use muqsit\invmenu\InvMenuHandler;
use Clouria\IslandArchitect\{
    runtime\TemplateIsland,
    runtime\sessions\PlayerSession,
    runtime\TemplateIslandGenerator,
    customized\skyblock\CustomSkyBlockCreateCommand};
use pocketmine\{
    Player,
    tile\Tile,
    tile\Chest,
    level\Level,
    utils\Utils,
    math\Vector3,
    plugin\PluginBase,
    level\format\Chunk,
    math\AxisAlignedBB,
    scheduler\ClosureTask,
    utils\TextFormat as TF,
    level\particle\RedstoneParticle,
    level\generator\GeneratorManager};
use function substr;
use function strtolower;
use function file_exists;
use function array_search;
use function class_exists;

class IslandArchitect extends PluginBase {

	private static $instance = null;

	/**
	 * @var PlayerSession[]
	 */
	private $sessions = [];

    public function onLoad() : void {
		self::$instance = $this;
	}

	public function onEnable() : void {
		$this->initConfig();
		if (class_exists(InvMenuHandler::class)) if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
		$class = EventListener::getClass();
		$this->getServer()->getPluginManager()->registerEvents(new $class, $this);

		$class = TemplateIslandGenerator::getClass();
		if (is_a($class, TemplateIslandGenerator::class, true)) $genname = $class::GENERATOR_NAME;
		else throw new \RuntimeException();
		GeneratorManager::addGenerator($class, $genname, true);

		$class = IslandArchitectCommand::getClass();
		$this->getServer()->getCommandMap()->register($this->getName(), new $class);

		if (SkyBlock::getInstance() !== null and SkyBlock::getInstance()->isEnabled()) $this->initDependency();

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $ct) : void {
		    foreach ($this->getSessions() as $s) if ($s->getIsland() !== null) {
		        $sb = $s->getPlayer()->getTargetBlock(12);
		        $sc = $s->getIsland()->getStartCoord();
                $ec = $s->getIsland()->getEndCoord();

		        // Send random generation block popup
		        $r = $s->getIsland()->getRandomByVector3($sb);
		        if ($r !== null) $s->getPlayer()->sendPopup(TF::YELLOW . 'Random generation block: ' . TF::BOLD . TF::GOLD . $s->getIsland()->getRandomLabel($r));

		        // Island chest coord popup
                $chest = $s->getIsland()->getChest();
                if ($chest !== null and $chest->asVector3()->equals($sb->asVector3())) $s->getPlayer()->sendPopup(TF::YELLOW . 'Island chest block' . "\n" . TF::ITALIC . TF::GRAY . '(Click to view or edit contents)');

		        // Draw island area outline
                if ($sc !== null and $ec !== null and $s->getPlayer()->getLevel()->getFolderName() === $s->getIsland
                    ()->getLevel()) {
                    $bb = new AxisAlignedBB(
                        min($sc->getFloorX(), $ec->getFloorX()),
                        min($sc->getFloorY(), $ec->getFloorY()),
                        min($sc->getFloorZ(), $ec->getFloorZ()),
                        max($sc->getFloorX(), $ec->getFloorX()),
                        max($sc->getFloorY(), $ec->getFloorY()),
                        max($sc->getFloorZ(), $ec->getFloorZ())
                    );
                    $bb->offset(0.5, 0.5, 0.5);
                    $bb->expand(0.5, 0.5, 0.5);
                    $distance = $s->getPlayer()->getViewDistance();
                    $dbb = (new AxisAlignedBB(
                    ($s->getPlayer()->getFloorX() >> 4) - $distance,
                    0,
                    ($s->getPlayer()->getFloorZ() >> 4) - $distance,
                    ($s->getPlayer()->getFloorX() >> 4) + $distance,
                    Level::Y_MAX,
                    ($s->getPlayer()->getFloorZ() >> 4) + $distance
                    ))->expand(1, 1, 1);
                    for ($x = $bb->minX; $x <= $bb->maxX; ++$x)
                    for ($y = $bb->minY; $y <= $bb->maxY; ++$y)
                    for ($z = $bb->minZ; $z <= $bb->maxZ; ++$z)
                    {
                        if (!$dbb->isVectorInside(new Vector3((int)$x >> 4, (int)$y >> 4, (int)$z >> 4))) continue;
                        if (
                            $x !== $bb->minX and
                            $y !== $bb->minY and
                            $z !== $bb->minZ and
                            $x !== $bb->maxX and
                            $y !== $bb->maxY and
                            $z !== $bb->maxZ
                        ) continue;
                        $s->getPlayer()->getLevel()->addParticle(new RedstoneParticle(new Vector3($x ,$y, $z), 10),
                            [$s->getPlayer()]);
                    }
                }
            }
        }), 10);
	}

    /**
     * @internal
     */
	public function initDependency() : void {
	    $pl = SkyBlock::getInstance();
	    $map = $pl->getCommandMap();
	    $cmd = $map->getCommand('create');
	    if ($cmd !== null) $pl->getCommandMap()->unregisterCommand($cmd->getName());
	    $class = CustomSkyBlockCreateCommand::getClass();
	    $map->registerCommand(new $class($map));
    }

	private function initConfig() : void {
		$this->saveDefaultConfig();
		$conf = $this->getConfig();
		foreach ($all = $conf->getAll() as $k => $v) $conf->remove($k);

		$conf->set('island-data-folder', (string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/'));
		$conf->set('panel-allow-unstable-item', (bool)($all['panel-allow-unstable-item'] ?? true));
		$conf->set('panel-default-seed', ($pds = $all['panel-default-seed'] ?? null) === null ? null : (int)$pds);
		$conf->set('island-creation-command-mapping', (array)($all['island-creation-command-mapping'] ?? [
		    'generation-name-which-will-be' => 'exported-island-data-file.json',
            'use-in-island-creation-cmd' => 'relative-path/start-from/island-data-folder.json'
        ]));

		$conf->save();
		$conf->reload();
	}

    public function getSession(Player $player, bool $nonnull = false) : ?PlayerSession {
		if (($this->sessions[$player->getName()] ?? null) !== null) $s = $this->sessions[$player->getName()];
		elseif ($nonnull) $s = ($this->sessions[$player->getName()] = new PlayerSession($player));
		return $s ?? null;
	}

	public function mapGeneratorType(string $type) : ?string {
	    foreach ($this->getConfig()->get('island-creation-command-mapping', []) as $st => $file) if (strtolower($st) === strtolower($type)) {
	        $sf = $file;
	        break;
        }
	    if (!isset($sf)) return null;
	    $type = $sf;
	    if (
	        isset($type) and
            !(file_exists($type = Utils::cleanPath($type))) and
            !file_exists($type = Utils::cleanPath(
                (string)($all['island-data-folder'] ?? $this->getDataFolder() . 'islands/') .
                $type . (strtolower(substr($type, -5)) === '.json' ? '' : '.json')
            ))) $type = null;
	    return $type;
    }

    /**
     * @return PlayerSession[]
         */
	public function getSessions() : array {
	    return $this->sessions;
    }

    /**
     * @param PlayerSession $session
     * @return bool Return false if the session has already been disposed or not even in the sessions list
     */
    public function disposeSession(PlayerSession $session) : bool {
        if (($r = array_search($session, $this->sessions, true)) === false) return false;
        if ($this->sessions[$r]->getIsland()) $this->sessions[$r]->saveIsland();
        unset($this->sessions[$r]);
        return true;
    }

    /**
     * @var array<int, TemplateIsland>
     */
    private $chestqueue;

    public function queueIslandChestCreation(Level $level, TemplateIsland $island) : bool {
        if (($this->chestqueue[$level->getId()] ?? null) !== null) return false;
        $this->chestqueue[$level->getId()] = $island;
        return true;
    }

    public function createIslandChest(Level $level, Chunk $chunk) : bool {
        $is = $this->chestqueue[$level->getId()] ?? null;
        if ($is === null) return false;
        unset($this->chestqueue[$level->getId()]);
        $pos = $is->getChest();
        if ($chunk->getX() !== ($pos->getFloorX() >> 4) or $chunk->getZ() !== ($pos->getFloorZ() >> 4)) return false;

        $island = SkyBlock::getInstance()->getIslandManager()->getIsland($level->getName());
        if($island === null) return false;

        $chest = Tile::createTile(Tile::CHEST, $level, Chest::createNBT($pos));
        if (!$chest instanceof Chest) return false;
        foreach (SkyBlock::getInstance()->getSettings()->getChestContentByGenerator($is->getName()) as $item) $chest->getInventory()->addItem($item);
        return true;
    }

	public static function getInstance() : ?self {
		return self::$instance;
	}

}
