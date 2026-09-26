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

namespace IvanCraft623\RankSystem\session;

use IvanCraft623\RankSystem\event\UserPermissionRemoveEvent;
use IvanCraft623\RankSystem\event\UserPermissionSetEvent;
use IvanCraft623\RankSystem\event\UserRankRemoveEvent;
use IvanCraft623\RankSystem\event\UserRankSetEvent;

use IvanCraft623\RankSystem\provider\UserData;
use IvanCraft623\RankSystem\rank\Rank;
use IvanCraft623\RankSystem\RankSystem;

use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\UUID;

use function array_filter;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_merge;
use function count;
use function in_array;
use function is_string;
use function spl_object_id;
use function str_replace;
use function strtolower;

abstract class Session {
	protected RankSystem $plugin;

	protected string $name;

	/**
	 * Fork Foxy : identifiant unique du joueur, utilise comme cle en base.
	 * Vide a la construction d'une session hors ligne : il est resolu
	 * au chargement des donnees (ou genere de facon deterministe).
	 */
	protected string $uuid;

	protected bool $initialized = false;

	/** @var array<int, \Closure(): void> */
	protected array $onInits = [];

	/** @var RankWrapper[] */
	protected array $ranks = [];

	/** @var string[] */
	protected array $permissions = [];

	/** @var array<string, ?int> */
	protected array $userPermissions = [];

	/** @var array<int, \Closure(): Promise<bool>> */
	protected array $syncQueue = [];

	protected bool $synchronized = false;

	public function __construct(string $name, string $uuid = "") {
		$this->plugin = RankSystem::getInstance();
		$this->name = $name;
		$this->uuid = $uuid;

		$this->loadUserData();
	}

	public function isInitialized() : bool {
		return $this->initialized;
	}

	/**
	 * @param \Closure(): void $onInit
	 */
	public function onInitialize(\Closure $onInit) : void {
		if ($this->initialized) {
			$onInit();
		} else {
			$this->onInits[spl_object_id($onInit)] = $onInit;
		}
	}

	protected function loadUserData() : void {
		$provider = $this->plugin->getProvider();
		if ($this->uuid !== "") {
			# Session en ligne : chargement par UUID
			$provider->getUserData($this->uuid)->onCompletion(
				function (?UserData $userData) use ($provider) {
					if ($userData !== null) {
						# Le gamertag Xbox a pu changer : on garde le pseudo a jour
						if ($userData->getName() !== $this->name) {
							$provider->updateName($this->uuid, $this->name);
						}
						$this->onUserDataLoaded($userData);
						return;
					}
					# Aucune ligne pour cet UUID : une ligne a pu etre creee
					# hors ligne a partir du pseudo (UUID placeholder) -> claim
					$provider->getUserDataByName($this->name)->onCompletion(
						function (?UserData $byNameData) use ($provider) {
							if ($byNameData !== null) {
								$provider->claimUser($this->uuid, $this->name);
							}
							$this->onUserDataLoaded($byNameData);
						},
						fn() => throw new \Error("Failed to load " . $this->name . "' session")
					);
				},
				fn() => throw new \Error("Failed to load " . $this->name . "' session")
			);
			return;
		}

		# Session hors ligne : resolution de l'UUID par le pseudo
		$provider->getUserDataByName($this->name)->onCompletion(
			function (?UserData $userData) {
				if ($userData !== null) {
					$this->uuid = $userData->getUuid();
				} else {
					# Joueur jamais vu : UUID placeholder deterministe,
					# remplace par le vrai UUID au premier join (claim)
					$this->uuid = UUID::fromData($this->name)->toString();
				}
				$this->onUserDataLoaded($userData);
			},
			fn() => throw new \Error("Failed to load " . $this->name . "' session")
		);
	}

	/**
	 * Point commun des chargements en ligne / hors ligne :
	 * applique les donnees et debloque la file de synchronisation.
	 */
	private function onUserDataLoaded(?UserData $userData) : void {
		$permissions = [];
		if ($userData !== null) {
			# Ranks
			$this->syncRanks($userData->getRanks());

			# Permissions
			$permissions = $userData->getPermissions();
		}
		$this->syncPermissions($permissions);
		$this->updateRanks();

		$this->initialized = true;
		$this->synchronized = true;
		foreach ($this->onInits as $onInit) {
			$onInit();
		}
		$this->onInits = [];
	}

	/**
	 * Only get called when ranks were loaded or updated
	 * on database, don't call it directly.
	 *
	 * @param array<string, ?int> $ranksdata
	 *
	 * @internal
	 */
	public function syncRanks(array $ranksdata) : void {
		$this->ranks = [];
		$manager = $this->plugin->getRankManager();
		foreach ($ranksdata as $name => $expTime) {
			$rank = $manager->getRank($name);
			if ($rank !== null) {
				$this->ranks[spl_object_id($rank)] = new RankWrapper($rank, $expTime);
			}
		}
	}

	/**
	 * Only get called when permissions were loaded or updated
	 * on database, don't call it directly.
	 *
	 * @param array<string, ?int> $userPermissions
	 *
	 * @internal
	 */
	public function syncPermissions(array $userPermissions) : void {
		$this->permissions = [];
		$this->userPermissions = $userPermissions;

		foreach ($this->getRanks() as $rank) {
			$this->permissions = array_merge($this->permissions, $rank->getPermissions());
		}
		$this->permissions = array_merge($this->permissions, array_keys($userPermissions));

		# Fork Foxy : le noeud "*" donne toutes les permissions enregistrees
		# sur le serveur (rank Owner equivalent a un op).
		if (in_array("*", $this->permissions, true)) {
			$this->permissions = array_merge(
				array_keys(PermissionManager::getInstance()->getPermissions()),
				array_keys($userPermissions)
			);
		}
	}

	public function getName() : string {
		return $this->name;
	}

	/**
	 * Fork Foxy : UUID utilise comme cle en base de donnees.
	 */
	public function getUuid() : string {
		return $this->uuid;
	}

	abstract public function getPlayer() : ?Player;

	public function getNameTagFormat() : string {
		$format = $this->plugin->getConfig()->getNested("nametag.format", "{nametag_ranks_prefix}{nametag_name-color}{name}");
		if (!is_string($format)) {
			throw new AssumptionFailedError("Expected string for \"nametag.format\" config");
		}
		foreach ($this->plugin->getTagManager()->getTags() as $tag) {
			$format = str_replace($tag->getId(), $tag->getValue($this), $format);
		}
		return $format;
	}

	public function getChatFormat() : string {
		$format = $this->plugin->getConfig()->getNested("chat.format", "{chat_ranks_prefix}{chat_name-color}{name}{chat_format}{message}");
		if (!is_string($format)) {
			throw new AssumptionFailedError("Expected string for \"chat.format\" config");
		}
		foreach ($this->plugin->getTagManager()->getTags() as $tag) {
			$format = str_replace($tag->getId(), $tag->getValue($this), $format);
		}
		return $format;
	}

	/**
	 * They will always be ordered hierarchically
	 *
	 * @return Rank[]
	 */
	public function getRanks() : array {
		$ranks = array_map(function(RankWrapper $wrapper) {
			return $wrapper->getRank();
		}, $this->ranks);
		if (count($ranks) !== 0) {
			return $ranks;
		}
		return [$this->plugin->getRankManager()->getDefault()];
	}

	public function getHighestRank() : Rank {
		$ranks = $this->getRanks();
		return $ranks[array_key_first($ranks)];
	}

	/**
	 * Fork Foxy : le rank de moderation du joueur (il n'en a qu'un seul).
	 * getRanks() etant deja trie hierarchiquement, le premier trouve est le bon.
	 */
	public function getModerationRank() : ?Rank {
		foreach ($this->getRanks() as $rank) {
			if ($rank->isModeration()) {
				return $rank;
			}
		}
		return null;
	}

	/**
	 * Fork Foxy : le grade de jeu le plus haut du joueur.
	 * Retourne le rank par defaut si le joueur n'a aucun grade de jeu.
	 */
	public function getHighestGameRank() : Rank {
		foreach ($this->getRanks() as $rank) {
			if ($rank->isGame()) {
				return $rank;
			}
		}
		return $this->plugin->getRankManager()->getDefault();
	}

	/**
	 * Fork Foxy : grade de jeu a afficher.
	 * Retourne null si le joueur a un rank de moderation et que son plus
	 * haut grade de jeu est le rank par defaut : on n'affiche pas "Player"
	 * derriere un prefixe staff.
	 */
	public function getDisplayGameRank() : ?Rank {
		$game = $this->getHighestGameRank();
		if ($this->getModerationRank() !== null) {
			$default = $this->plugin->getRankManager()->getDefault();
			if (strtolower($game->getName()) === strtolower($default->getName())) {
				return null;
			}
		}
		return $game;
	}

	/**
	 * @return Rank[]
	 */
	public function getTempRanks() : array {
		return array_map(function(RankWrapper $wrapper) {
			return $wrapper->getRank();
		}, array_filter($this->ranks, function(RankWrapper $wrapper) {
			return $wrapper->isTemporary();
		}));
	}

	public function isTempRank(Rank|string $rank) : bool {
		$rank = ($rank instanceof Rank) ? $rank : $this->plugin->getRankManager()->getRank($rank);
		if ($rank !== null && isset($this->ranks[spl_object_id($rank)])) {
			return $this->ranks[spl_object_id($rank)]->isTemporary();
		}
		return false;
	}

	public function hasRank(Rank|string $rank) : bool {
		$rank = ($rank instanceof Rank) ? $rank : $this->plugin->getRankManager()->getRank($rank);
		return $rank !== null && isset($this->ranks[spl_object_id($rank)]);
	}

	public function getRankExpTime(Rank|string $rank) : ?int {
		$rank = ($rank instanceof Rank) ? $rank : $this->plugin->getRankManager()->getRank($rank);
		if ($rank !== null && isset($this->ranks[spl_object_id($rank)])) {
			return $this->ranks[spl_object_id($rank)]->getExpTime();
		}
		return null;
	}

	/**
	 * @param \Closure(): Promise<bool> $closure
	 */
	private function addToSyncQueue(\Closure $closure) : void {
		$this->syncQueue[] = $closure;
		if ($this->synchronized) {
			$this->synchronized = false;
			$this->loadSyncTask();
		}
	}

	private function loadSyncTask() : void {
		if (!$this->synchronized) {
			if (count($this->syncQueue) === 0) {
				$this->synchronized = true;
				return;
			}
			$key = array_key_first($this->syncQueue);
			$this->syncQueue[$key]()->onCompletion(function () use ($key) {
				unset($this->syncQueue[$key]);
				$this->loadSyncTask();
			},
			function () {
				// Do something...
			});
		}
	}

	public function setRank(Rank $rank, ?int $expTime = null) : bool {
		# Call Event
		$ev = new UserRankSetEvent(
			$this,
			$rank,
			$expTime
		);
		$ev->call();

		if ($ev->isCancelled()) {
			return false;
		}

		$default = $this->plugin->getRankManager()->getDefault();
		if ($rank === $default || $this->hasRank($rank)) {
			$ev->cancel();
			return false;
		}

		# Fork Foxy : un seul rank de moderation a la fois.
		# On retire l'ancien avant d'appliquer le nouveau (file de sync sequentielle).
		if ($rank->isModeration()) {
			$current = $this->getModerationRank();
			if ($current !== null && $current !== $rank) {
				$this->removeRank($current);
			}
		}

		$this->addToSyncQueue(function () use ($rank, $expTime) : Promise {
			/** @var PromiseResolver<bool> $resolver */
			$resolver = new PromiseResolver();
			$this->plugin->getProvider()->setRank($this->uuid, $this->name, $rank->getName(), $expTime)->onCompletion(
				function (array $ranks) use ($resolver) {
					$this->syncRanks($ranks);
					$this->syncPermissions($this->userPermissions);
					$this->updateRanks();
					$resolver->resolve(true);
				},
				fn() => $resolver->resolve(false)
			);
			return $resolver->getPromise();
		});
		return true;
	}

	public function removeRank(Rank $rank) : bool {
		# Call Event
		$ev = new UserRankRemoveEvent(
			$this,
			$rank
		);
		$ev->call();

		if ($ev->isCancelled()) {
			return false;
		}

		$default = $this->plugin->getRankManager()->getDefault();
		if ($rank === $default || !$this->hasRank($rank)) {
			$ev->cancel();
			return false;
		}

		$this->addToSyncQueue(function () use ($rank) : Promise {
			/** @var PromiseResolver<bool> $resolver */
			$resolver = new PromiseResolver();
			$this->plugin->getProvider()->removeRank($this->uuid, $rank->getName())->onCompletion(
				function (array $ranks) use ($resolver) {
					$this->syncRanks($ranks);
					$this->syncPermissions($this->userPermissions);
					$this->updateRanks();
					$resolver->resolve(true);
				},
				fn() => $resolver->resolve(false)
			);
			return $resolver->getPromise();
		});
		return true;
	}

	/**
	 * @return string[]
	 */
	public function getPermissions() : array {
		return $this->permissions;
	}

	/**
	 * @return string[]
	 */
	public function getUserPermissions() : array {
		return array_keys($this->userPermissions);
	}

	public function isTempPermission(string $permission) : bool {
		return $this->getPermissionExpTime($permission) !== null;
	}

	public function getPermissionExpTime(string $permission) : ?int {
		return $this->userPermissions[$permission] ?? null;
	}

	public function hasPermission(string $perm) : bool {
		return in_array($perm, $this->permissions, true);
	}

	public function hasUserPermission(string $perm) : bool {
		return array_key_exists($perm, $this->userPermissions);
	}

	public function setPermission(string $perm, ?int $expTime = null) : bool {
		# Call Event
		$ev = new UserPermissionSetEvent(
			$this,
			$perm,
			$expTime
		);
		$ev->call();

		if ($ev->isCancelled()) {
			return false;
		}

		$this->addToSyncQueue(function () use ($perm, $expTime) : Promise {
			/** @var PromiseResolver<bool> $resolver */
			$resolver = new PromiseResolver();
			$this->plugin->getProvider()->setPermission($this->uuid, $this->name, $perm, $expTime)->onCompletion(
				function (array $permissions) use ($resolver) {
					$this->syncPermissions($permissions);
					$this->updateRanks();
					$resolver->resolve(true);
				},
				fn() => $resolver->resolve(false)
			);
			return $resolver->getPromise();
		});
		return true;
	}

	public function removePermission(string $perm) : bool {
		# Call Event
		$ev = new UserPermissionRemoveEvent(
			$this,
			$perm
		);
		$ev->call();

		if ($ev->isCancelled()) {
			return false;
		}

		$this->addToSyncQueue(function () use ($perm) : Promise {
			/** @var PromiseResolver<bool> $resolver */
			$resolver = new PromiseResolver();
			$this->plugin->getProvider()->removePermission($this->uuid, $perm)->onCompletion(
				function (array $permissions) use ($resolver) {
					$this->syncPermissions($permissions);
					$this->updateRanks();
					$resolver->resolve(true);
				},
				fn() => $resolver->resolve(false)
			);
			return $resolver->getPromise();
		});
		return true;
	}

	public function updateRanks() : void {
		$this->ranks = array_map(function(Rank $rank) {
			return new RankWrapper($rank, $this->getRankExpTime($rank));
		}, $this->plugin->getRankManager()->getHierarchical($this->getRanks()));
	}
}
