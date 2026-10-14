<?php

declare(strict_types=1);

namespace aParticles;

use pocketmine\color\Color;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\EnchantParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\WaterParticle;
use RuntimeException;

final class aParticles extends PluginBase implements Listener{

	private const SUPPORTED_PARTICLES = ["flame", "lava", "heart", "water", "happy", "angry", "bubble", "portal", "enchant"];

	private Config $usersConfig;
	private aParticlesEconomyManager $economy;
	/** @var array<int|string, mixed> */
	private array $configData;
	/** @var array<string, array{particles: list<string>, enabled: string|false}> */
	private array $users = [];

	protected function onEnable() : void{
		$dataFolder = $this->getDataFolder();
		if(!is_dir($dataFolder) && !mkdir($dataFolder, 0777, true) && !is_dir($dataFolder)){
			throw new RuntimeException("Could not create the aParticles data directory.");
		}
		$this->saveResource("config.yml");
		$this->configData = (new Config($dataFolder . "config.yml", Config::YAML))->getAll();
		$this->usersConfig = new Config($dataFolder . "users.yml", Config::YAML, []);
		$this->users = $this->normalizeUsers($this->usersConfig->getAll());
		$this->economy = new aParticlesEconomyManager($this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function() : void{ $this->emitParticles(); }),
			$this->particlePeriod() * 20
		);
		$this->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function() : void{ $this->save(); }),
			6000
		);
		$this->getLogger()->info("§aHiệu Ứng Đã Được Kích Hoạt!");
		$this->getLogger()->info("§aTự Động Lưu Lại Sau 5 Phút...");
	}

	protected function onDisable() : void{
		if(isset($this->usersConfig)){
			$this->save();
		}
	}

	public function onPlayerJoin(PlayerJoinEvent $event) : void{
		$this->ensureUser($event->getPlayer());
	}

	/** @param list<string> $args */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(strtolower($command->getName()) !== "hieuung"){
			return false;
		}
		if(!($sender instanceof Player)){
			$sender->sendMessage("§cChỉ dành cho người chơi!");
			return true;
		}

		$this->ensureUser($sender);
		$name = strtolower($sender->getName());
		if(count($args) === 0){
			$sender->sendMessage($this->help($sender));
			return true;
		}
		if(count($args) === 1){
			$particle = strtolower($args[0]);
			if($particle === "help"){
				$sender->sendMessage($this->help($sender));
				return true;
			}
			if($particle === "save"){
				if(!$sender->hasPermission("aprt.save")){
					$sender->sendMessage($this->message("permNotExist", "§cBạn Không Có Quyền Sử Dụng Câu Lệnh Này"));
					return true;
				}
				$this->save();
				$sender->sendMessage($this->message("save", "§aĐã lưu dữ liệu hiệu ứng."));
				return true;
			}
			if($particle === "off"){
				$this->users[$name]["enabled"] = false;
				$this->save();
				$sender->sendMessage($this->message("particleOff", "§aHiệu ứng đã ngừng hoạt động."));
				return true;
			}
			if(!$this->isConfiguredParticle($particle)){
				$sender->sendMessage($this->message("particleNotExist", "§cHiệu ứng không tồn tại."));
				return true;
			}
			if(!$sender->hasPermission("aprt." . $particle)){
				$sender->sendMessage(str_replace("{particle}", $particle, $this->message("permNotExist", "§cBạn không có quyền dùng {particle}.")));
				return true;
			}
			if(!in_array($particle, $this->users[$name]["particles"], true)){
				$sender->sendMessage(str_replace("{particle}", $particle, $this->message("needToBuy", "§cBạn cần mua hiệu ứng {particle}.")));
				return true;
			}
			$this->users[$name]["enabled"] = $particle;
			$this->save();
			$sender->sendMessage(str_replace("{particle}", $particle, $this->message("enabled", "§aĐã bật hiệu ứng {particle}.")));
			return true;
		}
		if(count($args) === 2 && in_array(strtolower($args[0]), ["buy", "add"], true)){
			$particle = strtolower($args[1]);
			if(!$this->isConfiguredParticle($particle)){
				$sender->sendMessage($this->message("particleNotExist", "§cHiệu ứng không tồn tại."));
				return true;
			}
			if(in_array($particle, $this->users[$name]["particles"], true)){
				$sender->sendMessage(str_replace("{particle}", $particle, $this->message("bought", "§aBạn đã mua hiệu ứng {particle}.")));
				return true;
			}
			$price = $this->particlePrice($particle);
			if($price === null){
				$this->getLogger()->warning("Invalid configured price for particle " . $particle . ".");
				return true;
			}
			if($price > 0 && !$this->economy->buyParticle($sender, $price)){
				$sender->sendMessage($this->message("economyTransactionFailed", "§cGiao dịch không thành công."));
				return true;
			}
			$this->users[$name]["particles"][] = $particle;
			$this->save();
			$sender->sendMessage(str_replace("{particle}", $particle, $this->message("buy", "§aĐã mua hiệu ứng {particle}.")));
			return true;
		}
		$sender->sendMessage($this->help($sender));
		return true;
	}

	public function save() : void{
		$this->usersConfig->setAll($this->users);
		$this->usersConfig->save();
	}

	private function ensureUser(Player $player) : void{
		$name = strtolower($player->getName());
		if(isset($this->users[$name])){
			return;
		}
		$this->users[$name] = ["particles" => $this->freeParticles(), "enabled" => false];
		$this->save();
	}

	/**
	 * @param array<int|string, mixed> $data
	 * @return array<string, array{particles: list<string>, enabled: string|false}>
	 */
	private function normalizeUsers(array $data) : array{
		$users = [];
		foreach($data as $rawName => $record){
			if(!is_string($rawName) || !is_array($record)){
				continue;
			}
			$rawParticles = $record["particles"] ?? [];
			$particles = [];
			if(is_array($rawParticles)){
				foreach($rawParticles as $particle){
					if(is_string($particle) && in_array($particle, self::SUPPORTED_PARTICLES, true) && !in_array($particle, $particles, true)){
						$particles[] = $particle;
					}
				}
			}
			$enabled = $record["enabled"] ?? false;
			if(!is_string($enabled) || !in_array($enabled, $particles, true)){
				$enabled = false;
			}
			$users[strtolower($rawName)] = ["particles" => $particles, "enabled" => $enabled];
		}
		return $users;
	}

	/** @return list<string> */
	private function freeParticles() : array{
		$free = [];
		foreach($this->configuredParticles() as $name => $price){
			if($price === "free" || (is_numeric($price) && (float) $price === 0.0)){
				$free[] = $name;
			}
		}
		return $free;
	}

	/** @return array<string, mixed> */
	private function configuredParticles() : array{
		$particles = $this->configData["particles"] ?? [];
		if(!is_array($particles)){
			return [];
		}
		$configured = [];
		foreach($particles as $name => $price){
			if(is_string($name) && in_array($name, self::SUPPORTED_PARTICLES, true)){
				$configured[$name] = $price;
			}
		}
		return $configured;
	}

	private function isConfiguredParticle(string $particle) : bool{
		return array_key_exists($particle, $this->configuredParticles());
	}

	private function particlePrice(string $particle) : ?float{
		$price = $this->configuredParticles()[$particle] ?? null;
		if($price === "free"){
			return 0.0;
		}
		if(!is_numeric($price)){
			return null;
		}
		$amount = (float) $price;
		if(!is_finite($amount) || $amount < 0 || round($amount, 2) !== $amount){
			return null;
		}
		return $amount;
	}

	private function particlePeriod() : int{
		$value = $this->configData["period"] ?? 5;
		if(!is_numeric($value) || !is_finite((float) $value) || (float) $value < 1 || (float) $value > intdiv(PHP_INT_MAX, 20)){
			$this->getLogger()->warning("Invalid particle period; using 5 seconds.");
			return 5;
		}
		return (int) $value;
	}

	private function emitParticles() : void{
		$changed = false;
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$name = strtolower($player->getName());
			$user = $this->users[$name] ?? null;
			if($user === null || $user["enabled"] === false){
				continue;
			}
			$particle = $this->createParticle($user["enabled"]);
			if($particle === null){
				$this->users[$name]["enabled"] = false;
				$changed = true;
				continue;
			}
			$position = $player->getPosition();
			$particlePosition = new Vector3(
				$position->getX() + $this->randomFloat(-1.2, 1.2),
				$position->getY() + $this->randomFloat(0.25, 1.5),
				$position->getZ() + $this->randomFloat(-1.2, 1.2)
			);
			$player->getWorld()->addParticle($particlePosition, $particle);
		}
		if($changed){
			$this->save();
		}
	}

	private function createParticle(string $name) : ?Particle{
		return match($name){
			"flame" => new FlameParticle(),
			"lava" => new LavaParticle(),
			"heart" => new HeartParticle(mt_rand(1, 3)),
			"water" => new WaterParticle(),
			"happy" => new HappyVillagerParticle(),
			"angry" => new AngryVillagerParticle(),
			"bubble" => new BubbleParticle(),
			"portal" => new PortalParticle(),
			"enchant" => new EnchantParticle(new Color(0, 0, 0, 255)),
			default => null
		};
	}

	private function randomFloat(float $min, float $max) : float{
		return $min + mt_rand() / mt_getrandmax() * ($max - $min);
	}

	private function help(Player $player) : string{
		$list = "";
		foreach($this->configuredParticles() as $particle => $price){
			$displayPrice = $price === "free" || (is_numeric($price) && (float) $price === 0.0)
				? $this->message("free", "Free")
				: str_replace("{price}", is_scalar($price) ? (string) $price : "?", $this->message("price", "{price}"));
			if(!$player->hasPermission("aprt." . $particle)){
				$displayPrice .= $this->message("dontHavePermissions", " | You do not have permission");
			}
			if(in_array($particle, $this->users[strtolower($player->getName())]["particles"], true)){
				$displayPrice .= str_replace("{particle}", $particle, $this->message("bought", " | Purchased: {particle}"));
			}else{
				$displayPrice .= str_replace("{particle}", $particle, $this->message("notBought", " | Not purchased: {particle}"));
			}
			$list .= str_replace(
				["{particle}", "{price}"],
				[$particle, $displayPrice . "\n"],
				$this->message("helpList", "{particle}: {price}")
			);
		}
		return $this->message("help", "Particle effects") . "\n" . $list;
	}

	private function message(string $key, string $fallback) : string{
		$value = $this->configData[$key] ?? $fallback;
		return is_string($value) ? $value : $fallback;
	}
}
