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

namespace IvanCraft623\RankSystem\provider;

use Closure;

use IvanCraft623\RankSystem\RankSystem;

use pocketmine\promise\Promise;

abstract class Provider {

	protected RankSystem $plugin;

	public function __construct() {
		$this->plugin = RankSystem::getInstance();
	}

	abstract public function load() : void;

	abstract public function unload() : void;

	abstract public function getName() : string;

	/**
	 * Fork Foxy : les donnees sont clavees par UUID, plus par pseudo.
	 * Le pseudo n'est utilise que pour l'affichage, la resolution des
	 * sessions hors ligne et le suivi des changements de gamertag.
	 */

	/**
	 * @phpstan-return Promise<?UserData>
	 */
	abstract public function getUserData(string $uuid) : Promise;

	/**
	 * Recherche par pseudo (sessions hors ligne, claim au join).
	 *
	 * @phpstan-return Promise<?UserData>
	 */
	abstract public function getUserDataByName(string $name) : Promise;

	/**
	 * @phpstan-return Promise<bool>
	 */
	abstract public function isInDb(string $uuid) : Promise;

	/**
	 * Reattache une ligne existante (creee par pseudo) au vrai UUID du joueur.
	 *
	 * @param null|Closure(): void $onSuccess
	 * @param null|Closure(): void $onError
	 */
	abstract public function claimUser(string $uuid, string $name, ?Closure $onSuccess = null, ?Closure $onError = null) : void;

	/**
	 * Met a jour le pseudo stocke (changement de gamertag Xbox).
	 *
	 * @param null|Closure(): void $onSuccess
	 * @param null|Closure(): void $onError
	 */
	abstract public function updateName(string $uuid, string $name, ?Closure $onSuccess = null, ?Closure $onError = null) : void;

	/**
	 * @param array<string, ?int>  $ranks
	 * @param null|Closure(): void $onSuccess
	 * @param null|Closure(): void $onError
	 */
	abstract public function setRanks(string $uuid, string $name, array $ranks, ?Closure $onSuccess = null, ?Closure $onError = null) : void;

	/**
	 * @phpstan-return Promise<array<string, ?int>>
	 */
	abstract public function setRank(string $uuid, string $name, string $rank, ?int $expTime = null) : Promise;

	/**
	 * @phpstan-return Promise<array<string, ?int>>
	 */
	abstract public function removeRank(string $uuid, string $rank) : Promise;

	/**
	 * @param array<string, ?int>  $permisions
	 * @param null|Closure(): void $onSuccess
	 * @param null|Closure(): void $onError
	 */
	abstract public function setPermissions(string $uuid, string $name, array $permisions, ?Closure $onSuccess = null, ?Closure $onError = null) : void;

	/**
	 * @phpstan-return Promise<array<string, ?int>>
	 */
	abstract public function setPermission(string $uuid, string $name, string $permission, ?int $expTime = null) : Promise;

	/**
	 * @phpstan-return Promise<array<string, ?int>>
	 */
	abstract public function removePermission(string $uuid, string $permission) : Promise;

	/**
	 * @param null|Closure(): void $onSuccess
	 * @param null|Closure(): void $onError
	 */
	abstract public function delete(string $uuid, ?Closure $onSuccess = null, ?Closure $onError = null) : void;
}
