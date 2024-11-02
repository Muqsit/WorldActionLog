<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

use pocketmine\player\Player;

final class Utils{

	/**
	 * @param Player $player
	 * @return array{player_xuid: string, player_uuid: string, player_gamertag: string}
	 */
	public static function writePlayer(Player $player) : array{
		return ["player_xuid" => $player->getXuid(), "player_uuid" => $player->getUniqueId()->toString(), "player_gamertag" => $player->getName()];
	}
}