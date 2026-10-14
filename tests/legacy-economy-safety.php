<?php

declare(strict_types=1);

namespace pocketmine\plugin {
	interface Plugin{
		public function getName() : string;
	}

	class PluginBase{
		private ?object $testServer = null;
		public function setTestServer(object $server) : void{ $this->testServer = $server; }
		public function getServer() : object{ return $this->testServer ?? throw new \RuntimeException("Missing test server."); }
		public function getLogger() : object{ return new \TestLogger(); }
	}
}

namespace {

require __DIR__ . "/../src/aParticles/aParticlesEconomyManager.php";

final class TestLogger{
	public function warning(string $message) : void{}
	public function info(string $message) : void{}
}

final class TestPluginManager{
	/** @param list<\pocketmine\plugin\Plugin> $plugins */
	public function __construct(private array $plugins){}
	public function getPlugin(string $name) : ?\pocketmine\plugin\Plugin{
		foreach($this->plugins as $plugin){
			if($plugin->getName() === $name){ return $plugin; }
		}
		return null;
	}
}

final class TestServer{
	public function __construct(private TestPluginManager $pluginManager){}
	public function getPluginManager() : TestPluginManager{ return $this->pluginManager; }
}

final class TestOwner extends \pocketmine\plugin\PluginBase{
	public function __construct(TestServer $server){ $this->setTestServer($server); }
}

final class TestEconomy implements \pocketmine\plugin\Plugin{
	public function __construct(
		private string $name,
		private int|float $balance,
		private int $reduceResult = 1,
		private bool $setSucceeds = true
	){}
	public function getName() : string{ return $this->name; }
	public function myMoney(\pocketmine\player\Player|string $player) : int|float{ return $this->balance; }
	public function reduceMoney(\pocketmine\player\Player|string $player, float $amount) : int{
		if($this->reduceResult === 1 && $this->balance >= $amount){ $this->balance -= $amount; }
		return $this->reduceResult;
	}
	public function getMoney(string $player) : int|float{ return $this->balance; }
	public function setMoney(string $player, int|float $amount) : bool{
		if(!$this->setSucceeds){ return false; }
		$this->balance = $amount;
		return true;
	}
	public function getBalance() : int|float{ return $this->balance; }
}

function expectSame(mixed $expected, mixed $actual, string $message) : void{
	if($expected !== $actual){
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

function createManager(?TestEconomy $economy) : \aParticles\aParticlesEconomyManager{
	$plugins = $economy === null ? [] : [$economy];
	return new \aParticles\aParticlesEconomyManager(new TestOwner(new TestServer(new TestPluginManager($plugins))));
}

$missing = createManager(null);
expectSame(null, $missing->getMoney("player"), "A missing economy must not appear as a zero balance.");
expectSame(false, $missing->buyParticle("player", 10.0), "A missing economy must not approve a purchase.");

$economy = new TestEconomy("EconomyAPI", 25);
$manager = createManager($economy);
expectSame(false, $manager->buyParticle("player", 30.0), "Insufficient funds must reject the purchase.");
expectSame(25, $economy->getBalance(), "Rejected purchases must not change the balance.");
expectSame(false, $manager->buyParticle("player", -1.0), "Negative prices must be rejected.");
expectSame(true, $manager->buyParticle("player", 10.0), "A confirmed EconomyAPI charge should succeed.");
expectSame(15.0, $economy->getBalance(), "A successful purchase must deduct once.");

$cancelled = new TestEconomy("EconomyAPI", 25, -2);
expectSame(false, createManager($cancelled)->buyParticle("player", 10.0), "Cancelled transactions must fail.");
expectSame(25, $cancelled->getBalance(), "Cancelled transactions must not grant a purchase.");

$pocketMoney = new TestEconomy("PocketMoney", 25);
expectSame(true, createManager($pocketMoney)->buyParticle("player", 5.0), "A verified setMoney backend should succeed.");
expectSame(20.0, $pocketMoney->getBalance(), "A verified backend must deduct once.");

$failingPocketMoney = new TestEconomy("PocketMoney", 25, 1, false);
expectSame(false, createManager($failingPocketMoney)->buyParticle("player", 5.0), "A rejected setMoney call must fail.");
expectSame(25, $failingPocketMoney->getBalance(), "A rejected setMoney call must not grant a purchase.");

$unsupported = createManager(new TestEconomy("UnknownEconomy", 100));
expectSame(null, $unsupported->getMoney("player"), "Unknown providers must not be treated as supported.");
expectSame(false, $unsupported->buyParticle("player", 10.0), "Unknown providers must not approve purchases.");

fwrite(STDOUT, "aBuyParticles API 5 economy behavior checks passed." . PHP_EOL);
}
