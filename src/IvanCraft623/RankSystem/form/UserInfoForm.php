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

namespace IvanCraft623\RankSystem\form;

use IvanCraft623\RankSystem\rank\Rank;
use IvanCraft623\RankSystem\rank\RankManager;
use IvanCraft623\RankSystem\session\Session;
use IvanCraft623\RankSystem\utils\Utils;
use jojoe77777\FormAPI\SimpleForm;

use pocketmine\player\Player;
use function str_replace;
use function time;

final class UserInfoForm {

	public function __construct() {
	}

	public function send(Player $player, Session $session, bool $manage = false) : void {
		$session->onInitialize(function () use ($player, $session, $manage) {
			$form = new SimpleForm(function (Player $player, int $result = null) use ($session) {
				if ($result === null) {
					return;
				}
				switch ($result) {
					case 0:
						$ranks = [];
						foreach (RankManager::getInstance()->getAll() as $rank) {
							if (!$session->hasRank($rank)) $ranks[] = $rank;
						}
						FormManager::getInstance()->sendSelectRank($player, "Set rank", $ranks)->onCompletion(
							function (Rank $rank) use ($player, $session) {
								FormManager::getInstance()->sendConfirmation($player, "Set rank", "Do you want the rank to expire?")->onCompletion(
									function (bool $expire) use ($player, $session, $rank) {
										if ($expire) {
											FormManager::getInstance()->sendInsertTime($player, "Set rank", "§7The rank will expire after this time has elapsed.")->onCompletion(
												function (int $time) use ($player, $session, $rank) {
													$session->setRank($rank, $time + time());
													$player->sendMessage("§a" . $rank->getName() . " §brank has been set to §e" . $session->getName() . " §bfor §a" . Utils::getTimeTranslated($time));
												}, function () {} // No response
											);
										} else {
											$session->setRank($rank);
											$player->sendMessage("§a" . $rank->getName() . " §brank has been set to §e" . $session->getName() . " §bfor §aNever");
										}
									}, function () {} // No response
								);
							}, function () {} // No response
						);
						break;

					case 1:
						FormManager::getInstance()->sendSelectRank($player, "Remove rank", $session->getRanks())->onCompletion(
							function (Rank $rank) use ($player, $session) {
								FormManager::getInstance()->sendConfirmation(
									$player, "Remove rank",
									"Do you want to §cremove §r" . $session->getName() . "'s " . $rank->getName() . " rank?"
								)->onCompletion(
									function (bool $remove) use ($player, $session, $rank) {
										$session->removeRank($rank);
										$player->sendMessage("§bYou have successfully §cremoved§b the §e" . $rank->getName() . " §brank from §a" . $session->getName());
									}, function () {} // No response
								);
							}, function () {} // No response
						);
						break;

					case 2:
						FormManager::getInstance()->sendInsertText($player, "Set permission", "§7Write permission", "Permission:")->onCompletion(
							function (string $permission) use ($player, $session) {
								if ($session->hasUserPermission($permission)) {
									$player->sendMessage("§c" . $session->getName() . " already has the " . $permission . " permission!");
								} else {
									FormManager::getInstance()->sendConfirmation($player, "Set permission", "Do you want the permission to expire?")->onCompletion(
										function (bool $expire) use ($player, $session, $permission) {
											if ($expire) {
												FormManager::getInstance()->sendInsertTime($player, "Set permission", "§7The permission will expire after this time has elapsed.")->onCompletion(
													function (int $time) use ($player, $session, $permission) {
														$session->setPermission($permission, $time + time());
														$player->sendMessage("§a" . $permission . " §bpermission has been set to §e" . $session->getName() . " §bfor §a" . Utils::getTimeTranslated($time));
													}, function () {} // No response
												);
											} else {
												$session->setPermission($permission);
												$player->sendMessage("§a" . $permission . " §bpermission has been set to §e" . $session->getName() . " §bfor §aNever");
											}
										}, function () {} // No response
									);
								}
							}, function () {} // No response
						);
						break;

					case 3:
						FormManager::getInstance()->sendInsertText($player, "Remove permission", "§7Write permission", "Permission:")->onCompletion(
							function (string $permission) use ($player, $session) {
								if (!$session->hasUserPermission($permission)) {
									$player->sendMessage("§c" . $session->getName() . " does not has the " . $permission . " permission!");
									return;
								}
								FormManager::getInstance()->sendConfirmation(
									$player, "Remove permission",
									"Do you want to §cremove §r" . $session->getName() . "'s " . $permission . " permission?"
								)->onCompletion(
									function (bool $remove) use ($player, $session, $permission) {
										$session->removePermission($permission);
										$player->sendMessage("§bYou have successfully §cremoved§b the §e" . $permission . " §bpermission from §a" . $session->getName());
									}, function () {} // No response
								);
							}, function () {} // No response
						);
						break;

					default:
						# Close Form
						break;
				}
			});
			$form->setTitle("User Information");
			$permissions = "";
			foreach ($session->getUserPermissions() as $permission) {
				$time = $session->getPermissionExpTime($permission);
				if ($time !== null) {
					$time = $time - time();
					if ($time < 0) {
						$time = null;
					}
				}
				$permissions .= "\n §e - " . $permission . " §7(" . ($time === null ? "Never" : Utils::getTimeTranslated($time)) . ")";
			}
			$ranks = "";
			foreach ($session->getRanks() as $rank) {
				$time = $session->getRankExpTime($rank);
				if ($time !== null) {
					$time = $time - time();
					if ($time < 0) {
						$time = null;
					}
				}
				$ranks .= "\n §e - " . $rank->getName() . " §7(" . ($time === null ? "Never" : Utils::getTimeTranslated($time)) . ")";
			}
			$form->setContent(
				"§r§fUser: §a" . $session->getName() . "\n\n" .
				"§r§fNametag: " . $session->getNameTagFormat() . "\n" .
				"§r§fChat: " . str_replace("{message}", "Hello world!", $session->getChatFormat()) . "\n\n" .
				"§r§fRanks: " . $ranks . "\n" .
				"§r§fPermissions: §a" . $permissions
			);
			if ($manage) {
				$form->addButton("Set rank", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/book_edit_default");
				$form->addButton("Remove rank", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/book_edit_default");
				$form->addButton("Set permission", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/book_edit_default");
				$form->addButton("Remove permission", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/book_edit_default");
			}
			$form->sendToPlayer($player);
		});
	}
}
