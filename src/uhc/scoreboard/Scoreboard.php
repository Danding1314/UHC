<?php

declare(strict_types=1);

namespace uhc\scoreboard;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Server;
use uhc\PlayerSession;
use function count;
use function str_repeat;

class Scoreboard {

	/** @var string */
	private const CRITERIA_NAME = "dummy";
	/** @var string */
	private const OBJECTIVE_NAME = "objective";

	/** @var int */
	public const SORT_ASCENDING = 0;
	/** @var int */
	public const SORT_DESCENDING = 1;

	/** @var int */
	public const MAX_LINES = 15;

	/** @var string */
	public const SLOT_BELOWNAME = "belowname";
	public const SLOT_LIST = "list";
	public const SLOT_SIDEBAR = "sidebar";

	/** @var PlayerSession */
	private $session;

	/** @var string */
	private $displayName = "";
	/** @var string */
	private $displaySlot = "";
	/** @var int */
	private $sortOrder = -1;

	/** @var bool */
	private $doesExist = false;

	/** @var string[] */
	private $lines = [];

	public function __construct(PlayerSession $player){
		$this->session = $player;
	}

	public function getSession() : PlayerSession{
		return $this->session;
	}

	public function getDisplayName() : string{
		return $this->displayName;
	}

	public function setDisplayName(string $displayName) : void{
		$this->displayName = $displayName;
	}

	public function getDisplaySlot() : string{
		return $this->displaySlot;
	}

	public function setDisplaySlot(string $displaySlot) : void{
		$this->displaySlot = $displaySlot;
	}

	public function getSortOrder() : int{
		return $this->sortOrder;
	}

	public function setSortOrder(int $sortOrder) : void{
		$this->sortOrder = $sortOrder;
	}

	public function exists() : bool{
		return $this->doesExist;
	}

	public function remove() : void{
		if($this->exists()){
			$pk = new RemoveObjectivePacket;
			$pk->objectiveName = self::OBJECTIVE_NAME;
			$this->getSession()->getPlayer()->sendDataPacket($pk);
			$this->doesExist = false;
		}
	}

	/**
	 * This initiates or updates the scoreboard, depending on if they have one or not.
	 *
	 * @param string $displayName
	 * @param string $displaySlot
	 * @param int $sortOrder
	 */
	public function send(string $displayName, string $displaySlot = self::SLOT_SIDEBAR, int $sortOrder = self::SORT_DESCENDING) : void{
		$pk = new SetDisplayObjectivePacket;
		$pk->displaySlot = $displaySlot;
		$pk->objectiveName = self::OBJECTIVE_NAME;
		$pk->displayName = $displayName;
		$pk->criteriaName = self::CRITERIA_NAME;
		$pk->sortOrder = $sortOrder;
		$this->getSession()->getPlayer()->sendDataPacket($pk);
		if(!$this->exists()){
			$this->doesExist = true;
			$this->displayName = $displayName;
			$this->displaySlot = $displaySlot;
			$this->sortOrder = $sortOrder;
		}

	}

	/**
	 * Used to update without changing the displayName
	 */
	public function update() : void{
		$this->send($this->getDisplayName(), $this->getDisplaySlot(), $this->getSortOrder());
	}

	/**
	 * @param string $message
	 * @param bool $update
	 */
	public function addLine(string $message, bool $update = true) : void{
		$this->setLine(count($this->lines), $message, $update);
	}

	/**
	 * @param int $line
	 * @param string $message
	 * @param bool $update
	 */
	public function setLine(int $line, string $message, bool $update = true) : void{
		if(!$this->exists()){
			Server::getInstance()->getLogger()->error("Use Scoreboard::send() before executing this function!");
			return;
		}
		$this->removeLine($line);

		$this->lines[$line] = $message;

		$entry = new ScorePacketEntry;
		$entry->customName = $message . str_repeat("\0", $line);
		$entry->objectiveName = self::OBJECTIVE_NAME;
		$entry->score = $line;
		$entry->scoreboardId = $line;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;

		$pk = new SetScorePacket;
		$pk->entries[] = $entry;
		$pk->type = SetScorePacket::TYPE_CHANGE;

		$this->getSession()->getPlayer()->sendDataPacket($pk);

		if($update) $this->update();
	}

	public function setLineArray(array $lines, bool $update = true) : void{
		foreach($lines as $key => $message){
			$this->setLine($key, $message, $update);
		}
	}

	public function clearLines(): void{
		foreach($this->lines as $lineId => $line){
			$this->removeLine($lineId);
		}
	}

	public function removeLine(int $line, bool $update = true) : void{
		if(isset($this->lines[$line])){
			unset($this->lines[$line]);
		}
		$entry = new ScorePacketEntry;
		$entry->customName = "";
		$entry->objectiveName = self::OBJECTIVE_NAME;
		$entry->score = $line;
		$entry->scoreboardId = $line;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;

		$pk = new SetScorePacket;
		$pk->entries[] = $entry;
		$pk->type = SetScorePacket::TYPE_REMOVE;

		$this->getSession()->getPlayer()->sendDataPacket($pk);

		if($update) $this->update();
	}

	public function setEmptyLine(int $line) : void{
		$text = str_repeat(" ", $line);
		$this->setLine($line, $text);
	}
}
