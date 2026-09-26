<?php

/*
 *   ____             _     ____
 *  |  _ \ __ _ _ __ | | __/ ___| _   _ ___| |_ ___ _ __ ___
 *  | |_) / _` | '_ \| |/ /\___ \| | | / __| __/ _ \ '_ ` _ \
 *  |  _ < (_| | | | |   <  ___) | |_| \__ \ ||  __/ | | | | |
 *  |_| \_\__,_|_| |_|_|\_\|____/ \__, |___/\__\___|_| |_| |_|
 *                                |___/
 *
 * An amazing rank and permissions manager for PocketMine-MP.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\RankSystem;

use IvanCraft623\RankSystem\command\RankSystemCommand;
use IvanCraft623\RankSystem\form\FormManager;
use IvanCraft623\RankSystem\migrator\LegacyRankSystem;
use IvanCraft623\RankSystem\migrator\MigratorManager;
use IvanCraft623\RankSystem\migrator\PurePerms;
use IvanCraft623\RankSystem\provider\libasynql as libasynqlProvider;
use IvanCraft623\RankSystem\provider\Provider;
use IvanCraft623\RankSystem\rank\RankManager;
use IvanCraft623\RankSystem\session\SessionManager;
use IvanCraft623\RankSystem\tag\TagManager;
use IvanCraft623\RankSystem\task\SponsorsListTask;
use IvanCraft623\RankSystem\task\UpdateTask;

use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use function count;
use function file_exists;
use function is_string;
use function mkdir;
use function strpos;
use function strtolower;

class RankSystem extends PluginBase {
	use SingletonTrait;

	public const DONATIONS_URL = "https://donate.endergames.org/IvanCraft623";

	public const CONFIG_VERSION = 2;

	/** @var mixed[] */
	private static array $globalPerms = [];

	/** @var Permission[] */
	private static array $pmDefaultPerms = [];

	private Provider $provider;

	/** @var string[] */
	private array $sponsors = [];

	public function onLoad() : void {
		self::setInstance($this);

		$this->reloadConfig();

		self::$globalPerms = (array) $this->getConfig()->get("Global_Perms", []);
		$this->saveResources();
		$this->getRankManager()->load();
		$this->getTagManager()->registerDefaults();
	}

	public function onEnable() : void {
		$this->loadCommands();
		$this->loadListeners();
		$this->loadProvider();
		$this->loadMigrators();
		$this->getScheduler()->scheduleRepeatingTask(new UpdateTask(), 60);
		$this->getServer()->getAsyncPool()->submitTask(
			new SponsorsListTask(self::DONATIONS_URL . "?info=members", function(?array $sponsors) {
				if ($sponsors !== null) {
					$this->sponsors = $sponsors;
				} else {
					$this->getLogger()->warning("Failed to fetch sponsors list");
				}
			})
		);
	}

	public function getProvider() : Provider {
		return $this->provider;
	}

	public function getSessionManager() : SessionManager {
		return SessionManager::getInstance();
	}

	public function getTagManager() : TagManager {
		return TagManager::getInstance();
	}

	public function getFormManager() : FormManager {
		return FormManager::getInstance();
	}

	public function getRankManager() : RankManager {
		return RankManager::getInstance();
	}

	public function getMigratorManager() : MigratorManager {
		return MigratorManager::getInstance();
	}

	public function getConfigs(string $value) : Config {
		return new Config(self::getInstance()->getDataFolder() . $value, Config::YAML);
	}

	/**
	 * @return mixed[]
	 */
	public function getGlobalPerms() : array {
		return self::$globalPerms;
	}

	/**
	 * From PurePerms
	 * @return Permission[]
	 */
	public function getPmmpPerms() : array {
		if (count(self::$pmDefaultPerms) === 0) {
			foreach (PermissionManager::getInstance()->getPermissions() as $permission) {
				if (strpos($permission->getName(), "pocketmine") !== false) {
					self::$pmDefaultPerms[] = $permission;
				}
			}
		}
		return self::$pmDefaultPerms;
	}

	/**
	 * @return Permission[]
	 */
	public function getPluginPerms(PluginBase $plugin) : array {
		$pluginPerms = [];
		foreach ($plugin->getDescription()->getPermissions() as $default => $perms) {
			foreach ($perms as $perm) {
				$pluginPerms[] = $perm;
			}
		}
		return $pluginPerms;
	}

	/**
	 * @return string[]
	 */
	public function getSponsors() : array {
		return $this->sponsors;
	}

	public function saveResources() : void {
		$this->saveResource("config.yml");
		$this->saveResource("ranks.yml");
	}

	private function loadCommands() : void {
		$this->getServer()->getCommandMap()->register('RankSystem', new RankSystemCommand($this));
	}

	private function loadListeners() : void {
		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	}

	private function loadProvider() : void {
		if (!isset($this->provider)) {
			$database = (array) $this->getConfig()->get("database", []);
			$nameRaw = $database["type"] ?? "";
			$name = is_string($nameRaw) ? $nameRaw : "";
			switch (strtolower($name)) {
				case "sqlite":
				case "sqlite3":
				case "sq3":
				case "mysql":
				case "mysqli":
					$provider = libasynqlProvider::class;
					break;

				default:
					$this->getLogger()->critical("Unknown database type: " . $name);
					throw new DisablePluginException("Unknown database type: " . $name);
			}
			$this->setProvider($provider::getInstance());
		}
	}

	public function loadMigrators() : void {
		$migrator = $this->getMigratorManager();
		$migrator->register(new LegacyRankSystem());
		$migrator->register(new PurePerms());

		foreach ($migrator->getAll() as $migrator) {
			if ($migrator->canMigrate() && !$migrator->hasMigrated()) {
				$this->getLogger()->notice("Migrating data from: " . $migrator->getName());
				if ($migrator->migrate()) {
					$this->getLogger()->info("§a" . $migrator->getName() . " data has migrated successfully!");
				} else {
					$this->getLogger()->warning("Failed to migrate data from " . $migrator->getName());
				}
			}
		}
	}

	public function setProvider(Provider $provider) : void {
		$databaseFolder = $this->getDataFolder() . "database";
		if (!file_exists($databaseFolder)) {
			mkdir($databaseFolder, 0777);
		}
		$provider->load();
		$this->provider = $provider;
		$this->getLogger()->info("User provider was set to: " . $provider->getName());
	}
}
