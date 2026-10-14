<?php

declare(strict_types=1);

namespace aParticles;

use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;

final class aParticlesEconomyManager{

	private ?Plugin $economy = null;

	public function __construct(PluginBase $plugin){
		$pluginManager = $plugin->getServer()->getPluginManager();
		foreach(["EconomyAPI", "PocketMoney", "MassiveEconomy"] as $pluginName){
			$candidate = $pluginManager->getPlugin($pluginName);
			if($candidate instanceof Plugin && $this->supports($candidate)){
				$this->economy = $candidate;
				$plugin->getLogger()->info("§aĐã tìm thấy Plugin kinh tế: §d" . $candidate->getName());
				return;
			}
		}
		$plugin->getLogger()->warning("Không có Plugin kinh tế được hỗ trợ.");
	}

	public function getMoney(Player|string $player) : int|float|null{
		if($this->economy === null){
			return null;
		}
		$name = $player instanceof Player ? $player->getName() : $player;
		if($this->economy->getName() === "EconomyAPI"){
			if(!method_exists($this->economy, "myMoney")){
				return null;
			}
			$balance = $this->economy->myMoney($player);
		}else{
			if(!method_exists($this->economy, "getMoney")){
				return null;
			}
			$balance = $this->economy->getMoney($name);
		}
		if((!is_int($balance) && !is_float($balance) && !(is_string($balance) && is_numeric($balance))) || !is_finite((float) $balance) || (float) $balance < 0){
			return null;
		}
		return is_int($balance) ? $balance : (float) $balance;
	}

	public function buyParticle(Player|string $player, float $amount) : bool{
		if($this->economy === null || !is_finite($amount) || $amount <= 0){
			return false;
		}
		$balance = $this->getMoney($player);
		if($balance === null || $balance < $amount){
			return false;
		}
		if($this->economy->getName() === "EconomyAPI"){
			if(!method_exists($this->economy, "reduceMoney")){
				return false;
			}
			try{
				return $this->economy->reduceMoney($player, $amount) === 1;
			}catch(\Throwable){
				return false;
			}
		}

		$name = $player instanceof Player ? $player->getName() : $player;
		$updatedBalance = $balance - $amount;
		if(!method_exists($this->economy, "setMoney")){
			return false;
		}
		try{
			$result = $this->economy->setMoney($name, $updatedBalance);
		}catch(\Throwable){
			return false;
		}
		if($result === false){
			return false;
		}
		$verifiedBalance = $this->getMoney($name);
		return $verifiedBalance !== null && abs($verifiedBalance - $updatedBalance) < 0.000001;
	}

	public function getEconomyPlugin(bool $name = false) : Plugin|string|null{
		if($this->economy === null){
			return null;
		}
		return $name ? $this->economy->getName() : $this->economy;
	}

	private function supports(Plugin $plugin) : bool{
		return match($plugin->getName()){
			"EconomyAPI" => method_exists($plugin, "myMoney") && method_exists($plugin, "reduceMoney"),
			"PocketMoney", "MassiveEconomy" => method_exists($plugin, "getMoney") && method_exists($plugin, "setMoney"),
			default => false
		};
	}
}
