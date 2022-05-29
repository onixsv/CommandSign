<?php

namespace solo\commandsign;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\utils\SignText;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\Position;

class CommandSign extends PluginBase implements Listener{

	public static string $prefix = "§d<§f시스템§d> §f";

	private Config $config;

	private array $commands;

	private Config $commandsConfig;

	public static function positionHash(Position $pos){
		return $pos->getWorld()->getFolderName() . ":" . $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
	}

	protected function onEnable() : void{
		@mkdir($this->getDataFolder());
		$this->saveResource("setting.yml");

		$this->config = new Config($this->getDataFolder() . "setting.yml", Config::YAML);

		$this->commandsConfig = new Config($this->getDataFolder() . "commands.yml", Config::YAML);

		$this->commands = $this->commandsConfig->getAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	protected function onDisable() : void{
		$this->commandsConfig->setAll($this->commands);
		$this->commandsConfig->save();
	}

	public function setCommandSign(Position $pos, string $command){
		$this->commands[self::positionHash($pos)] = $command;
	}

	public function getCommandSign(Position $pos){
		return $this->commands[self::positionHash($pos)] ?? null;
	}

	public function removeCommandSign(Position $pos){
		unset($this->commands[self::positionHash($pos)]);
	}

	/**
	 * @handleCancelled  true
	 * @priority        MONITOR
	 */
	public function handlePlayerInteract(PlayerInteractEvent $event){
		if($event->getAction() === 1){
			if($event->getBlock()->getId() === BlockLegacyIds::SIGN_POST || $event->getBlock()->getId() === BlockLegacyIds::WALL_SIGN){
				$commandLine = $this->getCommandSign($event->getBlock()->getPosition());
				if($commandLine !== null){
					if(!$event->getPlayer()->hasPermission("commandsign.use")){
						return;
					}
					static $s = [];
					$player = $event->getPlayer();
					if(!isset($s[$player->getName()]) || (int) date("s") !== $s[$player->getName()]){
						$commandEv = new PlayerCommandPreprocessEvent($event->getPlayer(), "/" . $commandLine);
						$commandEv->call();
						if($commandEv->isCancelled()){
							return;
						}
						$event->cancel();
						$this->getServer()->dispatchCommand($event->getPlayer(), substr($commandEv->getMessage(), 1));
						$s[$player->getName()] = (int) date("s");
					}
				}
			}
		}
	}

	/**
	 * @ignoreCancelled true
	 * @priority        MONITOR
	 */
	public function handleBlockBreak(BlockBreakEvent $event){
		if($this->getCommandSign($event->getBlock()->getPosition()) !== null){
			$this->removeCommandSign($event->getBlock()->getPosition());
			$event->getPlayer()->sendMessage(CommandSign::$prefix . "표지판 명령을 삭제하였습니다.");
		}
	}

	/**
	 * @ignoreCancelled true
	 * @priority        HIGH
	 */
	public function handleSignChange(SignChangeEvent $event){
		$lines = $event->getNewText()->getLines();
		if(array_shift($lines) === "표명표명표명"){
			if(!$event->getPlayer()->hasPermission("commandsign.install")){
				return;
			}
			$commandLine = implode($lines);

			$newLines = explode("\n", str_ireplace("{COMMAND}", (trim($commandLine) == "") ? "§d명령어를 입력해주세요" : $commandLine, $this->config->get("commandsign-format", "§d<§f시스템§d>\n{COMMAND}")));
			$lines = [];
			for($i = 0; $i < 4; $i++){
				$lines[$i] = $newLines[$i] ?? "";
			}
			$event->setNewText(new SignText($lines));

			$this->setCommandSign($event->getBlock()->getPosition(), $commandLine);
		}
	}
}
