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
use pocketmine\level\format\Chunk;
use pocketmine\scheduler\AsyncTask;
use Clouria\IslandArchitect\IslandArchitect;
use Clouria\IslandArchitect\generator\TemplateIsland;
use function mkdir;
use function unlink;
use function substr;
use function serialize;
use function unserialize;
use function file_put_contents;

class IslandDataEmitTask extends AsyncTask {

    /**
     * @var string (Serialized TemplateIsland object)
     */
    protected $island;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string (Serialized Chunk[]|null)
     */
    protected $chunks;

    /**
     * @param TemplateIsland $island
     * @param Chunk[]|null $chunks If the array is not empty, an export action will be taken instead of normal save action
     * @param \Closure|null $callback Compatible with <code>function(string $file) {}</code>
     * @param \Closure|null $onerror
     */
    public function __construct(TemplateIsland $island, ?array $chunks, ?\Closure $callback = null, ?\Closure $onerror = null) {
        $this->island = serialize($island);
        $this->chunks = serialize($chunks);
        $this->path = IslandArchitect::getInstance()->getDataFolder() . 'islands/';

        $this->storeLocal([$callback, $onerror]);
    }

    public function onRun() : void {
        $island = unserialize($this->island);
        $chunks = unserialize($this->chunks);

        if (empty($chunks ?? [])) $data = $island->save();
        else $data = $island->export($chunks);
        $path = Utils::cleanPath($this->path);
        @mkdir($path);
        $path = $path . ($path[-1] === '/' ? '' : '/') . $island->getName() . '.isarch-templis';
        if (file_put_contents($path, $data)) @unlink(substr($path, 22) . 'json');

        $this->setResult($path);
    }

    public function onCompletion(Server $server) : void {
        $callback = $this->fetchLocal()[0];
        if (isset($callback)) $callback($this->getResult());
    }
}