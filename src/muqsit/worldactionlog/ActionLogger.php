<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

use Closure;
use Generator;
use JsonException;
use SOFe\AwaitGenerator\Await;
use function array_column;
use function array_map;
use function count;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class ActionLogger{

	/** @var array<string, Closure(string, int, int, int, array<string, string>) : string> */
	public array $formatters = [];

	public function __construct(
		readonly public Database $database
	){}

	/**
	 * @param string $action
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param array<string, string> $tags
	 * @return string
	 */
	public function format(string $action, string $world, int $x, int $y, int $z, array $tags) : string{
		if(isset($this->formatters[$action])){
			return $this->formatters[$action]($world, $x, $y, $z, $tags);
		}
		try{
			$tag_strings = json_encode($tags, JSON_THROW_ON_ERROR);
		}catch(JsonException){
			$tag_strings = "?";
		}
		return $tag_strings;
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
	public function create(string $world, int $x, int $y, int $z, string $action, int $timestamp) : Generator{
		return yield from $this->database->createEntry($world, $x, $y, $z, $action, $timestamp);
	}

	/**
	 * @param int $id
	 * @param array<string, string> $tags
	 * @return Generator
	 */
	public function setTags(int $id, array $tags) : Generator{
		yield from Await::all(array_map(fn($name, $value) => $this->database->setEntryTag($id, $name, $value), array_keys($tags), $tags));
	}

	/**
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string $action
	 * @param int $timestamp
	 * @param array<string, string> $tags
	 * @return Generator
	 */
	public function log(string $world, int $x, int $y, int $z, string $action, int $timestamp, array $tags) : Generator{
		$id = yield from $this->create($world, $x, $y, $z, $action, $timestamp);
		yield from $this->setTags($id, $tags);
	}

	/**
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param float $radius
	 * @param string|null $action
	 * @return Generator<mixed, Await::RESOLVE, void, int>
	 */
	public function getAroundCount(string $world, int $x, int $y, int $z, float $radius, ?string $action = null) : Generator{
		return yield from $this->database->countEntryHeadersAround($world, $x, $y, $z, $radius, $action);
	}

	/**
	 * @param string $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param float $radius
	 * @param int $offset
	 * @param int $length
	 * @param string|null $action
	 * @return Generator<mixed, Await::RESOLVE, void, list<array{id: int, x: int, y: int, z: int, action: string, timestamp: int, tags: array<string, string>}>>
	 */
	public function getAround(string $world, int $x, int $y, int $z, float $radius, int $offset, int $length, ?string $action = null) : Generator{
		$headers = yield from $this->database->selectEntryHeadersAround($world, $x, $y, $z, $radius, $offset, $length, $action);
		if(count($headers) === 0){
			return [];
		}
		$tags = yield from Await::all(array_map(fn($id) => $this->database->selectEntryTags($id), array_column($headers, "id")));
		return array_map(fn($header, $index) => $header + ["tags" => $tags[$index]], $headers, array_keys($headers));
	}

	public function close() : void{
		$this->database->close();
	}
}