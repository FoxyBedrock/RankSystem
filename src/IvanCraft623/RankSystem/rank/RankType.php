<?php

declare(strict_types=1);

namespace IvanCraft623\RankSystem\rank;

use function is_string;
use function strtolower;
use function trim;

/**
 * Fork Foxy : deux familles de ranks.
 *
 * - MODERATION : staff (Owner, Admin, ...). Un seul a la fois par joueur,
 *   affiche en toutes circonstances.
 * - GAME : grades de joueurs (Supreme, Prestige, ...). Cumulables,
 *   seul le plus haut est affiche.
 */
enum RankType : string {

	case MODERATION = "moderation";

	case GAME = "game";

	/**
	 * Resout une valeur de config vers un type, "game" par defaut.
	 */
	public static function fromConfig(mixed $value) : self {
		if (is_string($value)) {
			$type = self::tryFrom(strtolower(trim($value)));
			if ($type !== null) {
				return $type;
			}
		}
		return self::GAME;
	}

	public function isModeration() : bool {
		return $this === self::MODERATION;
	}

	public function isGame() : bool {
		return $this === self::GAME;
	}
}
