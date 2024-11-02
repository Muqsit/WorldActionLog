<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

final class DefaultActions{

	public const NAMESPACE = "wal:";

	public const BLOCK_ENTITY_BREAK = self::NAMESPACE . "block_entity_break";
	public const BLOCK_ENTITY_INTERACT = self::NAMESPACE . "block_entity_interact";
	public const BLOCK_ENTITY_PLACE = self::NAMESPACE . "block_entity_place";
	public const CHUNK_ENTER = self::NAMESPACE . "chunk_enter";
	public const CHUNK_EXIT = self::NAMESPACE . "chunk_exit";
	public const INVENTORY_OPEN = self::NAMESPACE . "inventory_open";
}