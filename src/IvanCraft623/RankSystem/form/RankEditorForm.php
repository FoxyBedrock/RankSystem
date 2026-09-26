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
use IvanCraft623\RankSystem\RankSystem;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

use pocketmine\player\Player;
use function explode;
use function implode;

/**
 * @phpstan-import-type NameTagFormat from Rank
 * @phpstan-import-type ChatFormat from Rank
 */
final class RankEditorForm {

	/**
	 * @param NameTagFormat $nametag
	 * @param ChatFormat    $chat
	 * @param string[]      $permissions
	 * @param string[]      $inheritance
	 */
	public function __construct(
		private string $name,
		private array $nametag = ["prefix" => "", "nameColor" => "§f"],
		private array $chat = ["prefix" => "", "nameColor" => "§f", "chatFormat" => "§e: §7"],
		private array $permissions = [],
		private array $inheritance = []
	) {
	}

	private function save() : void {
		RankSystem::getInstance()->getRankManager()->saveRankData($this->name, $this->nametag, $this->chat, $this->permissions, $this->inheritance);
	}

	public function send(Player $player) : void {
		$form = new SimpleForm(function (Player $player, int $result = null) {
			if ($result === null) {
				return;
			}
			switch ($result) {
				case 0:
					$this->sendNametagForm($player);
				break;

				case 1:
					$this->sendChatForm($player);
				break;

				case 2:
					$this->sendPermissionsForm($player);
				break;

				case 3:
					$this->sendInheritanceForm($player);
				break;

				case 4:
					$this->save();
				break;

				default:
					# Close Form
				break;
			}
		});
		$form->setTitle("Rank Editor");
		$form->setContent(
			"§fRank: §a" . $this->name . "\n\n" .
			"§fNametag: " . $this->nametag["prefix"] . $this->nametag["nameColor"] . "Steve" . "\n" .
			"§fChat: " . $this->chat["prefix"] . $this->chat["nameColor"] . "Steve" . $this->chat["chatFormat"] . "Hello world!"
		);
		$form->addButton("Nametag", SimpleForm::IMAGE_TYPE_PATH, "textures/items/name_tag");
		$form->addButton("Chat", SimpleForm::IMAGE_TYPE_PATH, "textures/gui/newgui/Language18");
		$form->addButton("Permissions", SimpleForm::IMAGE_TYPE_PATH, "textures/items/map_filled");
		$form->addButton("Inheritance", SimpleForm::IMAGE_TYPE_PATH, "textures/gui/newgui/Local");
		$form->addButton("Save and Exit", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/check");
		$form->addButton("Exit", SimpleForm::IMAGE_TYPE_PATH, "textures/blocks/barrier");
		$form->sendToPlayer($player);
	}

	private function sendNametagForm(Player $player) : void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result !== null) {
				$data = $result;
				unset($data[0]);
				/** @phpstan-var NameTagFormat $data */
				$this->nametag = $data;
			}
			$this->send($player);
		});
		$form->setTitle("Rank Editor");
		$form->addLabel("§7Modify the data to your liking!");
		$form->addInput("Prefix:", "", $this->nametag["prefix"], "prefix");
		$form->addInput("Name Color:", "", $this->nametag["nameColor"], "nameColor");
		$form->sendToPlayer($player);
	}

	private function sendChatForm(Player $player) : void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result !== null) {
				$data = $result;
				unset($data[0]);
				/** @phpstan-var ChatFormat $data */
				$this->chat = $data;
			}
			$this->send($player);
		});
		$form->setTitle("Rank Editor");
		$form->addLabel("§7Modify the data to your liking!");
		$form->addInput("Prefix:", "", $this->chat["prefix"], "prefix");
		$form->addInput("Name Color:", "", $this->chat["nameColor"], "nameColor");
		$form->addInput("Chat Format:", "", $this->chat["chatFormat"], "chatFormat");
		$form->sendToPlayer($player);
	}

	private function sendPermissionsForm(Player $player) : void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result !== null) {
				$this->permissions = explode(", ", $result["permissions"]);
			}
			$this->send($player);
		});
		$form->setTitle("Rank Editor");
		$form->addLabel(
			"§7Modify the data to your liking!" . "\n\n" .
			"Example: §eexample.permission, an.awasome.permission"
		);
		$form->addInput("Permissions:", "", implode(", ", $this->permissions), "permissions");
		$form->sendToPlayer($player);
	}

	private function sendInheritanceForm(Player $player) : void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result !== null) {
				$this->inheritance = explode(", ", $result["inheritance"]);
			}
			$this->send($player);
		});
		$form->setTitle("Rank Editor");
		$form->addLabel(
			"§7It will inherit the permissions from other ranks." . "\n\n" .
			"Example: §eAdmin, Owner"
		);
		$form->addInput("Inheritance:", "", implode(", ", $this->inheritance), "inheritance");
		$form->sendToPlayer($player);
	}
}
