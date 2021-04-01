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
namespace Clouria\IslandArchitect\runtime\sessions;

use jojoe77777\FormAPI\SimpleForm;
use Clouria\IslandArchitect\{
    IslandArchitect,
    runtime\TemplateIsland,
    conversion\IslandDataEmitTask};
use pocketmine\{
    Player,
    Server,
    level\Level,
    math\Vector3,
    scheduler\ClosureTask,
    utils\TextFormat as TF,
    level\particle\FloatingTextParticle};
use function max;
use function min;
use function count;
use function round;
use function get_class;
use function microtime;
use function class_exists;
use function spl_object_id;

class PlayerSession {

    const FLOATINGTEXT_SPAWN = 0;

    /**
	 * @var Player
	 */
	private $player;

    /**
     * @var array<mixed, FloatingTextParticle>
     */
    protected $floatingtext = [];

    /**
     * @var scalar[]
     */
    protected $viewingft = [];

    public function __construct(Player $player) {
		$this->player = $player;
	}

	public function getPlayer() : Player {
		return $this->player;
	}

	/**
	 * @var TemplateIsland|null
	 */
	protected $island = null;

	public function checkOutIsland(TemplateIsland $island) : void {
		if ($this->export_lock) {
            $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'An island is exporting in background, please wait until the island export is finished!');
            return;
        }
		$this->island = $island;

		$spawn = $island->getSpawn();
		if ($spawn === null) return;
		$spawn = $spawn->floor()->add(0.5, 0.5, 0.5);
		$ft = $this->getFloatingText(self::FLOATINGTEXT_SPAWN, true);
        $ft->setComponents($spawn->getX(), $spawn->getY(), $spawn->getZ());
        $ft->setText(TF::BOLD . TF::GOLD . 'Island spawn' . "\n" . TF::RESET . TF::GREEN . $spawn->getFloorX() . ', ' . $spawn->getFloorY() . ', ' . $spawn->getFloorZ());
        $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
	}

	public function getIsland() : ?TemplateIsland {
		return $this->island;
	}

	public function listRandoms() : void {
		if ($this->getIsland() === null) return;
		if (class_exists(SimpleForm::class)) {
			$f = new SimpleForm(function(Player $p, int $d = null) : void {
				if ($d === null) return;
				$this->editRandom($d);
			});
			foreach ($this->getIsland()->getRandoms() as $i => $r) $f->addButton(TF::BOLD . TF::DARK_BLUE . $this->getIsland()->getRandomLabel($i) . "\n" . TF::RESET . TF::ITALIC . TF::DARK_GRAY . '(' . count($r->getAllElements()) . ' elements)');
			$f->setTitle(TF::BOLD . TF::DARK_AQUA . 'Regex List');
			$f->addButton(TF::BOLD . TF::DARK_GREEN . 'New regex');
			$this->getPlayer()->sendForm($f);
		} else $this->editRandom();
	}

	public function close() : void {
        $this->saveIsland();
        if (!$this->getPlayer()->isOnline()) return;
        foreach ($this->floatingtext as $ft) {
            $ft->setInvisible();
            $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
        }
	}

	/**
	 * @var bool
	 */
	protected $save_lock = false;

	public function saveIsland() : void {
		if ($this->save_lock) return;
		if (($island = $this->getIsland()) === null) return;
		if (!$island->hasChanges()) return;
		$this->save_lock = true;
		$time = microtime(true);
		IslandArchitect::getInstance()->getLogger()->debug('Saving island "' . $island->getName() . '" (' . spl_object_id($island) . ')');
		$task = new IslandDataEmitTask($island, [], function() use ($island, $time) : void {
			$this->save_lock = false;
			IslandArchitect::getInstance()->getLogger()->debug('Island "' . $island->getName() . '" (' . spl_object_id($island) . ') save completed (' . round(microtime(true) - $time, 2) . 's)');
			$island->noMoreChanges();
		});
		Server::getInstance()->getAsyncPool()->submitTask($task);
	}

	/**
	 * @var bool
	 */
	private $export_lock = false;

	public function exportIsland() : void {
		if (($island = $this->getIsland()) === null) return;
		if (!$island->readyToExport()) {
			$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Please set the island start and end coordinate first!');
			return;
		}
		$this->export_lock = true;
		$this->island = null;
		$time = microtime(true);
		$this->getPlayer()->sendMessage(TF::YELLOW . 'Queued export task for island "' . $island->getName() . '"...');

		$sc = $island->getStartCoord();
		$ec = $island->getEndCoord();

		for ($x=min($sc->getFloorX(), $ec->getFloorX()) >> 4; $x <= (max($sc->getFloorX(), $ec->getFloorX()) >> 4); $x++) for ($z=min($sc->getFloorZ(), $ec->getFloorZ()) >> 4; $z <= (max($sc->getFloorZ(), $ec->getFloorZ()) >> 4); $z++) {
			while (($level = Server::getInstance()->getLevelByName($island->getLevel())) === null) {
				if ($wlock ?? false) {
					$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Island world (' . $island->getLevel() . ') is missing!');
					$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Export task aborted.');
					$this->export_lock = false;
					return;
				}
				Server::getInstance()->loadLevel($island->getLevel());
				$wlock = true;
			}
			$chunk = $level->getChunk($x, $z, true);
			if ($chunk === null) $this->getPlayer()->sendMessage(TF::BOLD . TF::YELLOW . 'Warning: ' . TF::RED . 'Failed to load required chunk ' . $x . ', ' . $z);
			else {
				$chunks[0][$hash = Level::chunkHash($x, $z)] = $chunk->fastSerialize();
				$chunks[1][$hash] = get_class($chunk);
			}
		}
		if (!isset($chunks)) {
		    $this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Critical: Failed to load required chunks');
		    return;
        }
		$this->getPlayer()->sendMessage(TF::GOLD . 'Start exporting...');
		$task = new IslandDataEmitTask($island, $chunks, function() use ($time) : void {
			$this->export_lock = false;
			$this->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Export completed!' . TF::ITALIC . TF::GRAY . ' (' . round(microtime(true) - $time, 2) . 's)');
		});
		$checker = null;
		$checker = IslandArchitect::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $ct) use (&$checker, $task, &$island) : void {
			if ($task->isCrashed()) {
				$this->export_lock = false;
				$this->getPlayer()->sendMessage(TF::BOLD . TF::RED . 'Critical: Export task crashed' . TF::ITALIC . TF::GRAY . ' (The selected region might be too big or an unexpected error occurred)');
				$this->getPlayer()->sendMessage(TF::BOLD . TF::GOLD . 'Attempting to recover original island settings...');
				$this->checkOutIsland($island);
				$restore = new IslandDataEmitTask($island, [], function() : void {
					$this->getPlayer()->sendMessage(TF::BOLD . TF::GREEN . 'Restore succeed!');
				});
				Server::getInstance()->getAsyncPool()->submitTask($restore);
			}
			if (!$this->export_lock) $checker->cancel();
		}), 10);

		Server::getInstance()->getAsyncPool()->submitTask($task);
	}

    /**
     * @param Player $player
     * @param PlayerSession|null $session
     * @return bool true = No island checked out
     */
	public static function errorCheckOutRequired(Player $player, ?PlayerSession $session) : bool {
		if ($session !== null and $session->getIsland() !== null) return false;
		$player->sendMessage(TF::BOLD . TF::RED . 'Please check out an island first!' . TF::GRAY . TF::ITALIC . ' ("/ia island <Island data file name: string>")');
		return true;
	}

    /**
     * @param scalar $id
     * @param bool $nonnull
     * @return FloatingTextParticle|null
     */
    public function getFloatingText($id, bool $nonnull = false) : ?FloatingTextParticle {
	    if (isset($this->floatingtext[$id])) return $this->floatingtext[$id];
	    if ($nonnull) return ($this->floatingtext[$id] = new FloatingTextParticle(new Vector3(0, 0, 0), ''));
	    return null;
    }

    /**
     * @param scalar $id
     * @return bool
     */
    public function showFloatingText($id) : bool {
	    if (!isset($this->floatingtext[$id])) return false;
	    if (in_array($id, $this->viewingft, true)) return false;
	    $this->viewingft[] = $id;

	    $ft = $this->floatingtext[$id];
	    $ft->setInvisible(false);
	    $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
	    return true;
    }

    /**
     * @param scalar $id
     * @return bool
     */
    public function hideFloatingText($id) : bool {
        if (!isset($this->floatingtext[$id])) return false;
	    if (($r = array_search($id, $this->viewingft, true)) === false) return false;
        unset($this->viewingft[$r]);

	    $ft = $this->floatingtext[$id];
	    $ft->setInvisible(true);
	    $this->getPlayer()->getLevel()->addParticle($ft, [$this->getPlayer()]);
	    return true;
    }

    public function editRandom(int $regexid = null) : bool {
        if (count($this->getIsland()->getRandoms()) >= 0x7fffffff) return false;
        // 2147483647, max limit of int tag value and random generation regex number
        $form = new SimpleForm();
        return true;
    }
}