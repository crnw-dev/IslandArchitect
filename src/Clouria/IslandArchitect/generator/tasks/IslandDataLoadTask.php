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

namespace Clouria\IslandArchitect\generator\tasks;

use pocketmine\Server;
use pocketmine\utils\Utils;
use pocketmine\scheduler\AsyncTask;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\TemplateIsland;
use function is_file;
use function serialize;
use function unserialize;
use function file_get_contents;

class IslandDataLoadTask extends AsyncTask {

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $islandname;

    /**
     * @param \Closure|null $callback Compatible with <code>function(?<@link TemplateIsland> $island, string $filepath) {}</code>
     */
    public function __construct(string $islandname, ?\Closure $callback = null, ?\Closure $onerror = null) {
        $this->islandname = $islandname;
        $this->path = (string)IslandArchitect::getInstance()->getConfig()->get('island-data-folder', IslandArchitect::getInstance()->getDataFolder() . 'islands/');

        $this->storeLocal([$callback, $onerror]);
    }

    public function onRun() : void {
        if (
            is_file($spath = Utils::cleanPath($this->islandname)) or // ppath = Primary path (Don't question lol)
            is_file($spath = Utils::cleanPath($this->islandname) . '.json') or
            is_file($spath = Utils::cleanPath($this->path) . ($this->path[-1] === '/' ? '' : '/') . $this->islandname . '.json')
        ) $r = [serialize(TemplateIsland::load(file_get_contents($spath), $this->worker->getLogger()))];
        else $r = [serialize(null)];
        $r[] = Utils::cleanPath($this->path) . ($this->path[-1] === '/' ? '' : '/') . $this->islandname . '.json';
        $this->setResult($r);
    }

    public function onCompletion(Server $server) : void {
        $r = $this->getResult();
        $callback = $this->fetchLocal()[0];
        if (isset($callback)) $callback(unserialize($r[0]), $r[1]);
    }
}