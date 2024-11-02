<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

use Generator;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function array_column;
use function array_combine;
use function array_filter;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_values;
use function ceil;
use function count;
use function gmdate;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function str_starts_with;
use function strtr;
use function var_dump;

final class Loader extends PluginBase{

	public int $entries_per_page;
	private ActionLogger $action_logger;

	protected function onEnable() : void{
		[$this->entries_per_page, $actions, $action_formats] = $this->loadConfig();
		$this->action_logger = new ActionLogger(new Database($this));
		foreach($action_formats as $action => $format){
			$this->action_logger->formatters[$action] = fn(string $world, int $x, int $y, int $z, array $tags) => strtr($format, [
				"{world}" => $world,
				"{x}" => $x,
				"{y}" => $y,
				"{z}" => $z,
				...array_combine(array_map(fn($k) => "{{$k}}", array_keys($tags)), $tags)
			]);
		}
		new EventListener($this, array_filter($actions, fn($action) => str_starts_with($action, DefaultActions::NAMESPACE)));
	}

	protected function onDisable() : void{
		$this->action_logger->close();
	}

	/**
	 * @return array{int, list<string>, array<string, string>}
	 */
	private function loadConfig() : array{
		$entries_per_page = $this->getConfig()->get("entries-per-page", 25);
		if(!is_int($entries_per_page) || $entries_per_page < 1){
			$this->getLogger()->warning("'entries-per-page' is improperly configured in config.yml");
			$this->getLogger()->warning("An integer value >= 1 is required, but a value of {$entries_per_page} was supplied.");
			$entries_per_page = 25;
			$this->getLogger()->warning("A fallback value of {$entries_per_page} will be used.");
		}
		$enabled_actions = $this->getConfig()->get("enabled-actions");
		if(!is_array($enabled_actions) || !array_is_list($enabled_actions) || count(array_filter($enabled_actions, is_string(...))) !== count($enabled_actions)){
			$this->getLogger()->error("'enabled-actions' is improperly configured in config.yml");
			$this->getLogger()->error("The plugin will no longer log actions.");
			$enabled_actions = [];
		}
		$action_formats = $this->getConfig()->get("action-formats");
		if(is_array($action_formats)){
			$actions = array_column($action_formats, "action");
			$formats = array_column($action_formats, "format");
			if(count(array_filter($actions, is_string(...))) !== count($actions)){
				$actions = null;
			}elseif(count(array_filter($formats, is_string(...))) !== count($formats)){
				$actions = null;
			}else{
				$action_formats = array_combine($actions, $formats);
			}
		}else{
			$actions = null;
		}
		if($actions === null){
			$this->getLogger()->error("'action-formats' is improperly configured in config.yml");
			$this->getLogger()->error("The plugin will no longer format actions.");
			$action_formats = [];
		}
		return [$entries_per_page, $enabled_actions, $action_formats];
	}

	public function getActionLogger() : ActionLogger{
		return $this->action_logger;
	}

	public function sendMessage(CommandSender $sender, string $message) : void{
		if($sender instanceof Player && !$sender->isConnected()){
			return;
		}
		$sender->sendMessage($message);
	}

	/**
	 * @param CommandSender $sender
	 * @param string $label
	 * @param array $args
	 * @return Generator<mixed, Await::RESOLVE, void, void>
	 */
	private function onCommandAsync(CommandSender $sender, string $label, array $args) : Generator{
		$argc = count($args);
		if($argc === 1 || $argc === 2 || $argc === 3){ // /wal <radius> [action] [page=1]
			if(!($sender instanceof Player)){
				$sender->sendMessage(TextFormat::RED . "You may only use this command as a player.");
				return;
			}
			$pos = $sender->getPosition();
			$world = $pos->world->getFolderName();
			$x = $pos->getFloorX();
			$y = $pos->getFloorY();
			$z = $pos->getFloorZ();
			$radius = (float) $args[0];
			if(isset($args[1]) && is_numeric($args[1])){
				$action = "";
				$page = max(1, (int) $args[1]);
			}else{
				$action = $args[1] ?? "";
				$page = max(1, isset($args[2]) ? (int) $args[2] : 1);
			}
		}elseif($argc === 5 || $argc === 6 || $argc === 7){ // /wal <world> <x> <y> <z> <radius> [action] [page=1]
			[$world, $x, $y, $z, $radius] = $args;
			$x = (int) $x;
			$y = (int) $y;
			$z = (int) $z;
			$radius = (float) $radius;
			if(isset($args[5]) && is_numeric($args[5])){
				$action = "";
				$page = max(1, (int) $args[5]);
			}else{
				$action = $args[5] ?? "";
				$page = max(1, isset($args[6]) ? (int) $args[6] : 1);
			}
		}else{
			$message = TextFormat::BOLD . TextFormat::WHITE . "WorldActionLog Commands" . TextFormat::RESET . TextFormat::EOL;
			$message .= TextFormat::GRAY . "/{$label} <radius> [action] [page=1] - view logs around you" . TextFormat::EOL;
			$message .= TextFormat::GRAY . "/{$label} <world> <x> <y> <z> <radius> [action] [page=1] - view logs around a specific point";
			$sender->sendMessage($message);
			return;
		}

		$this->sendMessage($sender, TextFormat::GRAY . "Reading logs...");
		$count = yield from $this->action_logger->getAroundCount($world, $x, $y, $z, $radius, $action);
		$pages = (int) ceil($count / $this->entries_per_page);
		while(true){
			$offset = ($page - 1) * $this->entries_per_page;
			$length = $this->entries_per_page;
			$logs = yield from $this->action_logger->getAround($world, $x, $y, $z, $radius, $offset, $length, $action);
			if($page > 1 && count($logs) === 0){
				$page = 1;
			}else{
				break;
			}
		}
		if(count($logs) === 0){
			$this->sendMessage($sender, TextFormat::RED . "No logs found around this area.");
			return;
		}
		$this->sendMessage($sender, TextFormat::BOLD . TextFormat::WHITE . "WorldActionLog Logs " . TextFormat::RESET . TextFormat::GRAY . "({$page} / {$pages})");
		foreach($logs as ["id" => $id, "x" => $x, "y" => $y, "z" => $z, "action" => $action, "timestamp" => $timestamp, "tags" => $tags]){
			$message  = TextFormat::GRAY . ++$offset . ". (#{$id}) " . gmdate("Y-m-d H:i:s", $timestamp) . " ";
			$message .= TextFormat::WHITE . $action . TextFormat::GRAY . " ";
			$message .= $this->action_logger->format($action, $world, $x, $y, $z, $tags);
			$this->sendMessage($sender, $message);
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		Await::g2c($this->onCommandAsync($sender, $label, $args));
		return true;
	}
}