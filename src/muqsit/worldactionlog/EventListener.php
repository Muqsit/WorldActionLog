<?php

declare(strict_types=1);

namespace muqsit\worldactionlog;

use pocketmine\block\Block;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\tile\TileFactory;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use pocketmine\utils\Utils as PmUtils;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use function array_diff;
use function array_unique;
use function assert;
use function count;
use function implode;
use function in_array;
use function is_a;
use function time;

final class EventListener{

	readonly private ActionLogger $logger;
	readonly private TileFactory $tile_factory;

	/**
	 * @param Loader $loader
	 * @param list<string> $actions
	 */
	public function __construct(Loader $loader, array $actions){
		$this->logger = $loader->getActionLogger();
		$this->tile_factory = TileFactory::getInstance();

		$registered_actions = [];
		$skipped_actions = [];
		$manager = $loader->getServer()->getPluginManager();
		foreach((new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PRIVATE) as $method){
			foreach($method->getAttributes(EventListenerAttribute::class) as $attribute){
				$instance = $attribute->newInstance();
				assert($instance instanceof EventListenerAttribute);
				if(!in_array($instance->action_id, $actions, true)){
					$skipped_actions[] = $instance->action_id;
					continue;
				}

				$handler = $method->getClosure($this);
				$method->getNumberOfParameters() === 2 || throw new RuntimeException("Method " . PmUtils::getNiceClosureName($handler) . " must have exactly 2 parameters");
				$method->getNumberOfRequiredParameters() === 2 || throw new RuntimeException("Method " . PmUtils::getNiceClosureName($handler) . " must have exactly 2 required parameters");
				[$event_param, $action_param] = $method->getParameters();
				$event_param->getType() instanceof ReflectionNamedType || throw new RuntimeException("Method " . PmUtils::getNiceClosureName($handler) . " has invalid event parameter");
				is_a($event_param->getType()->getName(), Event::class, true) || throw new RuntimeException("Method " . PmUtils::getNiceClosureName($handler) . " has invalid event parameter of type {$event_param->getType()->getName()}");
				$action_param->getType() instanceof ReflectionNamedType || throw new RuntimeException("Method " . PmUtils::getNiceClosureName($handler) . " has invalid action parameter");

				$manager->registerEvent($event_param->getType()->getName(), fn($event) => $handler($event, $instance->action_id), EventPriority::MONITOR, $loader);
				$registered_actions[] = $instance->action_id;
			}
		}

		$unknown_actions = array_diff($actions, $registered_actions);
		if(count($unknown_actions) > 0){
			$loader->getLogger()->warning("Some defined actions are not known: " . implode(", ", $unknown_actions));
			$loader->getLogger()->warning("These actions will be ignored.");
			$loader->getLogger()->warning("Remove these actions from your config.yml to hide this message.");
		}
		if(count($skipped_actions) > 0){
			$loader->getLogger()->debug("The following actions have been skipped: " . implode(", ", $skipped_actions));
		}
		if(count($registered_actions) > 0){
			$loader->getLogger()->debug("The following actions are being listened: " . implode(", ", array_unique($registered_actions)));
		}else{
			$loader->getLogger()->debug("No default actions are being listened.");
		}
	}

	/**
	 * @param World $world
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string $action
	 * @param array<string, string> $tags
	 */
	private function log(World $world, int $x, int $y, int $z, string $action, array $tags) : void{
		Await::g2c($this->logger->log($world->getFolderName(), $x, $y, $z, $action, time(), $tags));
	}

	private function doGenericChunkLogging(Player $player, Position $from, Position $to, string $action) : void{
		$from_f = $from->floor();
		$to_f = $to->floor();
		if($from_f->x === $to_f->x && $from_f->y === $to_f->y && $from_f->z === $to_f->z && $from->world === $to->world){
			return;
		}

		$from_x = $from_f->x >> Chunk::COORD_BIT_SIZE;
		$from_z = $from_f->z >> Chunk::COORD_BIT_SIZE;
		$to_x = $to_f->x >> Chunk::COORD_BIT_SIZE;
		$to_z = $to_f->z >> Chunk::COORD_BIT_SIZE;
		if($from_x === $to_x && $from_z === $to_z){
			return;
		}

		$tags = Utils::writePlayer($player);
		if($action === DefaultActions::CHUNK_ENTER){
			$this->log($to->world, $to_f->x, $to_f->y, $to_f->z, $action, $tags);
		}elseif($action === DefaultActions::CHUNK_EXIT){
			$this->log($from->world, $from_f->x, $from_f->y, $from_f->z, $action, $tags);
		}else{
			throw new RuntimeException("Unknown action: {$action}");
		}
	}

	private function doGenericBlockEntity(Player $player, Block $block, int $x, int $y, int $z, string $action) : void{
		$tile_class = $block->getIdInfo()->getTileClass();
		if($tile_class === null){
			return;
		}
		$tags = Utils::writePlayer($player);
		$tags["block_entity"] = $this->tile_factory->getSaveId($tile_class);
		$this->log($player->getWorld(), $x, $y, $z, $action, $tags);
	}

	#[EventListenerAttribute(action_id: DefaultActions::BLOCK_ENTITY_BREAK)]
	private function onBlockBreak(BlockBreakEvent $event, string $action) : void{
		$block = $event->getBlock();
		$pos = $block->getPosition();
		$this->doGenericBlockEntity($event->getPlayer(), $block, $pos->x, $pos->y, $pos->z, $action);
	}

	#[EventListenerAttribute(action_id: DefaultActions::BLOCK_ENTITY_INTERACT)]
	private function onPlayerInteract(PlayerInteractEvent $event, string $action) : void{
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			$block = $event->getBlock();
			$pos = $block->getPosition();
			$this->doGenericBlockEntity($event->getPlayer(), $block, $pos->x, $pos->y, $pos->z, $action);
		}
	}

	#[EventListenerAttribute(action_id: DefaultActions::BLOCK_ENTITY_PLACE)]
	private function onBlockPlace(BlockPlaceEvent $event, string $action) : void{
		$player = $event->getPlayer();
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
			$this->doGenericBlockEntity($player, $block, $x, $y, $z, $action);
		}
	}

	#[EventListenerAttribute(action_id: DefaultActions::CHUNK_ENTER)]
	#[EventListenerAttribute(action_id: DefaultActions::CHUNK_EXIT)]
	private function onEntityTeleport(EntityTeleportEvent $event, string $action) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$this->doGenericChunkLogging($entity, $event->getFrom(), $event->getTo(), $action);
		}
	}

	#[EventListenerAttribute(action_id: DefaultActions::CHUNK_ENTER)]
	#[EventListenerAttribute(action_id: DefaultActions::CHUNK_EXIT)]
	private function onPlayerMove(PlayerMoveEvent $event, string $action) : void{
		$this->doGenericChunkLogging($event->getPlayer(), $event->getFrom(), $event->getTo(), $action);
	}

	#[EventListenerAttribute(action_id: DefaultActions::INVENTORY_OPEN)]
	private function onInventoryOpen(InventoryOpenEvent $event, string $action) : void{
		$inventory = $event->getInventory();
		if(!($inventory instanceof BlockInventory)){
			return;
		}

		$pos = $inventory->getHolder();
		$player = $event->getPlayer();
		$tags = Utils::writePlayer($player);
		$tile = $pos->world->getTileAt($pos->x, $pos->y, $pos->z);
		if($tile !== null){
			$tags["block_entity"] = $this->tile_factory->getSaveId($tile::class);
		}
		$this->log($pos->world, $pos->x, $pos->y, $pos->z, $action, $tags);
	}
}