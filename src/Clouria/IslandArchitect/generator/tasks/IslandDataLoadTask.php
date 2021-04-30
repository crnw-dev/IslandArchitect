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
use pocketmine\scheduler\AsyncTask;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\TemplateIsland;
use function serialize;
use function unserialize;
use function file_exists;
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
     * @param string $islandname
     * @param \Closure|null $callback Compatible with <code>function(?<@link TemplateIsland> $island, string $filepath) {}</code>
     * @param \Closure|null $onerror Compatible with <code>function(<@link \Throwable>$err) {}</code>
     */
    public function __construct(string $islandname, ?\Closure $callback = null, ?\Closure $onerror = null) {
        $this->islandname = $islandname;
        $this->path = IslandArchitect::getInstance()->getDataFolder() . 'islands/';

        $this->storeLocal([$callback, $onerror]);
    }

    public function onRun() : void {
        if (file_exists($spath = $this->path . $this->islandname . '.json')) $r = [serialize(TemplateIsland::load(file_get_contents($spath), $this->worker->getLogger()))];
        else $r = [serialize(null)];
        $r[] = $spath;
        $this->setResult($r);
    }

    public function onCompletion(Server $server) : void {
        $r = $this->getResult();
        $callback = $this->fetchLocal()[0];
        if (isset($callback)) $callback(unserialize($r[0]), $r[1]);
    }
}