<?php

namespace pocketmine\plugin {
	class PluginBase {
		private $testServer;

		public function setTestServer($server) {
			$this->testServer = $server;
		}

		public function getServer() {
			return $this->testServer;
		}

		public function getLogger() {
			return new \TestLogger();
		}
	}
}

namespace pocketmine\scheduler {
	class PluginTask {
	}
}

namespace pocketmine\event {
	interface Listener {
	}
}

namespace {
	require __DIR__ . '/../src/aParticles/aParticles.php';

	class TestLogger {
		public function warning($message) {
		}

		public function info($message) {
		}
	}

	class TestPluginManager {
		private $economy;

		public function __construct($economy) {
			$this->economy = $economy;
		}

		public function getPlugin($name) {
			return $this->economy !== null && $this->economy->getName() === $name ? $this->economy : null;
		}
	}

	class TestServer {
		private $pluginManager;

		public function __construct($pluginManager) {
			$this->pluginManager = $pluginManager;
		}

		public function getPluginManager() {
			return $this->pluginManager;
		}
	}

	class TestEconomy {
		private $name;
		private $balance;

		public function __construct($name, $balance) {
			$this->name = $name;
			$this->balance = $balance;
		}

		public function getName() {
			return $this->name;
		}

		public function myMoney($player) {
			return $this->balance;
		}

		public function setMoney($player, $amount) {
			$this->balance = $amount;
		}

		public function getBalance() {
			return $this->balance;
		}
	}

	function assertSameValue($expected, $actual, $message) {
		if ($expected !== $actual) {
			fwrite(STDERR, $message . PHP_EOL);
			exit(1);
		}
	}

	function createManager($economy) {
		$plugin = new \aParticles\aParticles();
		$plugin->setTestServer(new TestServer(new TestPluginManager($economy)));
		return new \aParticles\aParticlesEconomyManager($plugin);
	}

	$manager = createManager(null);
	assertSameValue(null, $manager->getMoney('player'), 'Missing economy must not appear as a zero balance.');
	assertSameValue(false, $manager->buyParticle('player', 10), 'Missing economy must not approve a purchase.');

	$economy = new TestEconomy('EconomyAPI', 25);
	$manager = createManager($economy);
	assertSameValue(false, $manager->buyParticle('player', 30), 'Insufficient funds must reject the purchase.');
	assertSameValue(25, $economy->getBalance(), 'Rejected purchase must not change the balance.');
	assertSameValue(false, $manager->buyParticle('player', -1), 'Negative prices must be rejected.');
	assertSameValue(true, $manager->buyParticle('player', 10), 'Sufficient funds must allow and verify the charge.');
	assertSameValue(15.0, $economy->getBalance(), 'Successful purchase must deduct the configured price once.');

	$unsupportedEconomy = new TestEconomy('UnsupportedEconomy', 100);
	$manager = createManager($unsupportedEconomy);
	assertSameValue(null, $manager->getMoney('player'), 'Unsupported economy APIs must not be treated as supported.');
	assertSameValue(false, $manager->buyParticle('player', 10), 'Unsupported economy APIs must not approve purchases.');
	assertSameValue(100, $unsupportedEconomy->getBalance(), 'Unsupported economy APIs must not mutate balances.');

	echo "Legacy economy safety checks passed." . PHP_EOL;
}
