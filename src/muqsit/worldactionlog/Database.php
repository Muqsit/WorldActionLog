<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

use Generator;
use Logger;
use pocketmine\Server;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;
use Symfony\Component\Filesystem\Path;
use function array_column;

final class Database{

	readonly private Logger $logger;
	readonly private Server $server;
	private DataConnector $connector;

	public function __construct(Loader $loader){
		$this->logger = $loader->getLogger();
		$this->server = $loader->getServer();
		$this->connector = libasynql::create($loader, [
			"type" => "sqlite",
			"sqlite" => ["file" => Path::join($loader->getDataFolder(), "data.sqlite")]
		], ["sqlite" => "database.sql"]);
		$this->connector->executeGeneric("worldactionlog.init.actions");
		$this->connector->executeGeneric("worldactionlog.init.actions_idx_world");
		$this->connector->executeGeneric("worldactionlog.init.actions_idx_coords");
		$this->connector->executeGeneric("worldactionlog.init.actions_tags");
	}

	public function close() : void{
		if(isset($this->connector)){
			$this->connector->close();
			unset($this->connector);
		}
	}

	/**
	 * @param string $query
	 * @param array<string, mixed> $args
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, mixed, array[]>
	 */
	private function asyncSelect(string $query, array $args = []) : Generator{
		try{
			return yield from $this->connector->asyncSelect($query, $args);
		}catch(SqlError $error){
			$this->logger->logException($error);
			$this->server->shutdown();
			yield from Await::promise(function($resolve) : void{}); // simulate 'never' return type
		}
	}

	/**
	 * @param string $query
	 * @param array<string, mixed> $args
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, mixed, array{int, int}>
	 */
	private function asyncInsert(string $query, array $args = []) : Generator{
		try{
			return yield from $this->connector->asyncInsert($query, $args);
		}catch(SqlError $error){
			$this->logger->logException($error);
			$this->server->shutdown();
			yield from Await::promise(function($resolve) : void{});
		}
	}

	/**
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string $action
	 * @param int $timestamp
	 * @return Generator<mixed, Await::RESOLVE, void, int>
	 */
	public function createEntry(string $world, int $x, int $y, int $z, string $action, int $timestamp) : Generator{
		[$insert_id, ] = yield from $this->asyncInsert("worldactionlog.insert.entry", ["world" => $world, "x" => $x, "y" => $y, "z" => $z, "action" => $action, "timestamp" => $timestamp]);
		return $insert_id;
	}

	/**
	 * @param int $id
	 * @param string $tag
	 * @param string $value
	 * @return Generator<mixed, Await::RESOLVE, void, void>
	 */
	public function setEntryTag(int $id, string $tag, string $value) : Generator{
		yield from $this->asyncInsert("worldactionlog.insert.tag", ["id" => $id, "tag" => $tag, "value" => $value]);
	}

	/**
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param float $radius
	 * @param int $offset
	 * @param int $length
	 * @param string $action
	 * @return Generator<mixed, Await::RESOLVE, void, list<array{id: int, x: int, y: int, z: int, action: string, timestamp: int}>>
	 */
	public function selectEntryHeadersAround(string $world, int $x, int $y, int $z, float $radius, int $offset, int $length, string $action) : Generator{
		return yield from $this->asyncSelect("worldactionlog.select.latest_headers", ["world" => $world, "x" => $x, "y" => $y, "z" => $z, "radius" => $radius, "offset" => $offset, "length" => $length, "action" => $action]);
	}

	/**
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param float $radius
	 * @param string $action
	 * @return Generator<mixed, Await::RESOLVE, void, list<array{id: int, action: string, timestamp: int}>>
	 */
	public function countEntryHeadersAround(string $world, int $x, int $y, int $z, float $radius, string $action) : Generator{
		$rows = yield from $this->asyncSelect("worldactionlog.select.latest_header_count", ["world" => $world, "x" => $x, "y" => $y, "z" => $z, "radius" => $radius, "action" => $action]);
		return $rows[0]["c"];
	}

	/**
	 * @param int $id
	 * @return Generator<mixed, Await::RESOLVE, void, array<string, string>>
	 */
	public function selectEntryTags(int $id) : Generator{
		$rows = yield from $this->asyncSelect("worldactionlog.select.tags_by_id", ["id" => $id]);
		return array_column($rows, "value", "tag");
	}
}