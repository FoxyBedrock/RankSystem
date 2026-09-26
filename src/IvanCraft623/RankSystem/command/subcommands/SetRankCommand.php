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

namespace IvanCraft623\RankSystem\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;

use IvanCraft623\RankSystem\command\args\RankArgument;
use IvanCraft623\RankSystem\command\args\TimeArgument;
use IvanCraft623\RankSystem\RankSystem;
use IvanCraft623\RankSystem\utils\Utils;

use pocketmine\command\CommandSender;
use function array_key_exists;
use function time;

final class SetRankCommand extends BaseSubCommand {

	public function __construct(private RankSystem $plugin) {
		parent::__construct("setrank", "Set a rank to a user", ["set"]);
		$this->setPermission("ranksystem.command.setrank");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("user"));
		$this->registerArgument(1, new RankArgument("rank"));
		$this->registerArgument(2, new TimeArgument("time", true));
	}

	/**
	 * @param mixed[] $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (array_key_exists("time", $args) && $args["time"] === "null") {
			$sender->sendMessage(
				"§cInvalid time provided!" . "\n" .
				"§aDuration arguments: y = year, M = month, w = week, d = day, h = hour, m = minute" . "\n" .
				"§eFor instance, 1y3M means one year and three months (this is the same as 15M). 1w2d12h means one week, two days, and twelve hours (this is the same as 9d12h)."
			);
		} else {
			$session = $this->plugin->getSessionManager()->get($args["user"]);
			$session->onInitialize(function () use ($session, $sender, $args) {
				if ($session->hasRank($args["rank"])) {
					$sender->sendMessage("§c" . $session->getName() . " already has the " . $args["rank"]->getName() . " rank!");
				} else {
					$time = isset($args["time"]) ? ((int) ($args["time"])) : null;

					$session->setRank($args["rank"], $time);
					$sender->sendMessage("§a" . $args["rank"]->getName() . " §brank has been set to §e" . $session->getName() . " §bfor §a" . (isset($args["time"]) ? Utils::getTimeTranslated($time - time()) : "Never"));
				}
			});
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
