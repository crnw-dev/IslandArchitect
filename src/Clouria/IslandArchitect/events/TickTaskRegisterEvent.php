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

namespace Clouria\IslandArchitect\events;

use pocketmine\scheduler\Task;

class TickTaskRegisterEvent extends IslandArchitectEvent {

    /**
     * @var Task
     */
    protected $task;
    /**
     * @var int
     */
    protected $period;

    /**
     * TickTaskRegisterEvent constructor.
     * @param Task $task
     * @param int $period
     */
    public function __construct(Task $task, int $period) {
        parent::__construct();
        $this->task = $task;
        $this->period = $period;
    }

    /**
     * @return int
     */
    public function getPeriod() : int {
        return $this->period;
    }

    /**
     * @return Task
     */
    public function getTask() : Task {
        return $this->task;
    }

    /**
     * @param int $period
     */
    public function setPeriod(int $period) : void {
        $this->period = $period;
    }

    /**
     * @param Task $task
     */
    public function setTask(Task $task) : void {
        $this->task = $task;
    }
}