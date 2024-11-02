<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

final class DefaultActions{

	public const string NAMESPACE = "wal:";

	public const string BLOCK_ENTITY_BREAK = self::NAMESPACE . "block_entity_break";
	public const string BLOCK_ENTITY_INTERACT = self::NAMESPACE . "block_entity_interact";
	public const string BLOCK_ENTITY_PLACE = self::NAMESPACE . "block_entity_place";
	public const string CHUNK_ENTER = self::NAMESPACE . "chunk_enter";
	public const string CHUNK_EXIT = self::NAMESPACE . "chunk_exit";
	public const string INVENTORY_OPEN = self::NAMESPACE . "inventory_open";
}