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

use IvanCraft623\RankSystem\utils\Utils;
use jojoe77777\FormAPI\SimpleForm;

use pocketmine\player\Player;

final class RankInfoForm {

	public function __construct() {
	}

	public function send(Player $player, Rank $rank) : void {
		$form = new SimpleForm(null);
		$form->setTitle("Rank Info");
		$nametag = $rank->getNameTagFormat();
		$chat = $rank->getChatFormat();
		$permissions = "";
		foreach ($rank->getPermissions() as $permission) {
			$permissions .= "\n §e - " . $permission;
		}
		$form->setContent(
			"§r§fRank: §a" . $rank->getName() . "\n\n" .
			"§r§fNametag: " . $nametag["prefix"] . $nametag["nameColor"] . "Steve" . "\n" .
			"§r§fChat: " . $chat["prefix"] . $chat["nameColor"] . "Steve" . $chat["chatFormat"] . "Hello world!" . "\n" .
			"§r§fInheritance: §a" . Utils::ranks2string($rank->getInheritance()) . "\n" .
			"§r§fPermissions: §a" . $permissions
		);
		$form->sendToPlayer($player);
	}
}
