<?php
declare(strict_types=1);
namespace customiesdevs\showcase;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\block\states\BlockStates;
use customiesdevs\customies\block\component\BlockComponents;
use customiesdevs\customies\item\{CustomiesItemFactory, ItemComponents};
use pocketmine\block\BlockTypeIds;
use pocketmine\command\{Command, CommandSender};
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\{Item, ItemTypeIds};
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class ShowcasePlugin extends PluginBase implements Listener {
	/** @var array<string, string> */
	private array $entries = [];

	protected function onEnable(): void {
		$this->saveDefaultConfig();
		foreach(ShowcaseBlock::PLAYABLE as $specimen){ $this->registerBlock($specimen, $specimen); }
		foreach(ShowcaseItem::PLAYABLE as $specimen){
			$id = ItemTypeIds::newId();
			CustomiesItemFactory::getInstance()->registerItem(static fn() => new ShowcaseItem($id,$specimen),'showcase:'.$specimen);
			$this->entries[$specimen] = 'item';
		}
		foreach(['prism_berry'=>PrismFood::class, 'prism_pickaxe'=>PrismPickaxe::class, 'prism_helmet'=>PrismHelmet::class, 'prism_pebble'=>PrismPebble::class] as $specimen=>$class){
			$id = ItemTypeIds::newId();
			CustomiesItemFactory::getInstance()->registerItem(static fn() => new $class($id),'showcase:'.$specimen);
			$this->entries[$specimen] = 'item';
		}
		if($this->getConfig()->get('component-laboratory',true)){
			foreach(ComponentCatalog::blocks() as $key=>$_){ $this->registerBlock('lab_block_'.$key,'lab_'.$key); }
			foreach(ComponentCatalog::items() as $key=>$_){
				$id = ItemTypeIds::newId();
				CustomiesItemFactory::getInstance()->registerItem(static fn() => new ShowcaseItem($id,'lab_'.$key),'showcase:lab_item_'.$key);
				$this->entries['lab_item_'.$key] = 'item';
			}
		}
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getLogger()->info('Registered '.count($this->entries).' specimens. Install CustomiesShowcase.mcpack on the server; /cshow kit starts the tour.');
	}

	private function registerBlock(string $identifier, string $specimen): void {
		$id = BlockTypeIds::newId();
		CustomiesBlockFactory::getInstance()->registerBlock(static fn() => new ShowcaseBlock($id,$specimen),'showcase:'.$identifier);
		$this->entries[$identifier] = 'block';
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if(!$sender->hasPermission('customiesshowcase.command')){ return true; }
		$action = strtolower($args[0] ?? 'list');
		if($action === 'list'){
			$filter = $args[1] ?? '';
			$entries = array_filter(array_keys($this->entries),static fn(string $id) => $filter === '' || str_contains($id,$filter));
			$sender->sendMessage('Showcase IDs ('.count($entries).'): '.implode(', ',$entries));
			$sender->sendMessage('/cshow give <id> [amount] | /cshow kit [page] | /cshow inspect [block]');
			return true;
		}
		if(!$sender instanceof Player){ $sender->sendMessage('This action requires an in-game player.'); return true; }
		if($action === 'give'){
			$id = str_replace('showcase:','',$args[1] ?? '');
			if(!isset($this->entries[$id])){ $sender->sendMessage('Unknown specimen. Use /cshow list [filter].'); return true; }
			$amount = filter_var($args[2] ?? '1', FILTER_VALIDATE_INT);
			if($amount === false || $amount < 1 || $amount > 64){ $sender->sendMessage('Amount must be an integer from 1 to 64.'); return true; }
			$item = $this->specimen($id);
			$item->setCount(min($amount,$item->getMaxStackSize()));
			$this->give($sender,$item);
			return true;
		}
		if($action === 'kit'){
			$page = filter_var($args[1] ?? '1', FILTER_VALIDATE_INT);
			$pages = array_chunk(array_keys($this->entries),9);
			if($page === false || !isset($pages[$page-1])){ $sender->sendMessage('Choose a kit page from 1 to '.count($pages).'.'); return true; }
			foreach($pages[$page-1] as $id){ $this->give($sender,$this->specimen($id)); }
			$sender->sendMessage('Kit page '.$page.'/'.count($pages).'. Existing inventory items are preserved.');
			return true;
		}
		if($action === 'inspect'){
			if(($args[1] ?? '') === 'block'){
				$block = $sender->getTargetBlock(6);
				if($block !== null){ $this->inspectBlock($sender,$block); }
			}else{
				$item = $sender->getInventory()->getItemInHand();
				$sender->sendMessage($item->getName().' | server type '.$item->getTypeId().' | stack '.$item->getMaxStackSize());
				$sender->sendMessage('Components: '.($item instanceof ItemComponents ? implode(', ',array_map(static fn($c) => $c->getName(),$item->getComponents())) : 'block item / vanilla'));
				$sender->sendMessage('NBT: '.$item->getNamedTag()->toString());
			}
			return true;
		}
		return false;
	}

	private function specimen(string $id): Item {
		$item = $this->entries[$id] === 'block' ? CustomiesBlockFactory::getInstance()->get('showcase:'.$id)->asItem() : CustomiesItemFactory::getInstance()->get('showcase:'.$id);
		if($id === 'prism'){
			$tag = $item->getNamedTag();
			$tag->setString('showcase:batch','gallery-01')->setInt('showcase:quality',3);
			$item->setNamedTag($tag)->setLore(['Batch: gallery-01','Quality: 3 / 5','Example of persistent per-stack NBT data']);
		}
		return $item;
	}

	private function give(Player $player, Item $item): void {
		// Never drop overflow into the world or remove existing inventory contents.
		if(!$player->getInventory()->canAddItem($item)){ $player->sendMessage('Inventory full: skipped '.$item->getName().'.'); return; }
		$player->getInventory()->addItem($item);
	}

	private function inspectBlock(Player $player, \pocketmine\block\Block $block): void {
		$states = [];
		if($block instanceof BlockStates){ foreach($block->getStates() as $s){ $states[$s->getName()] = $s->getCurrentValue(); } }
		$player->sendMessage($block->getName().' | states: '.json_encode($states));
		if($block instanceof BlockComponents){ $player->sendMessage('Components: '.implode(', ',array_map(static fn($c) => $c->getName(),$block->getComponents()))); }
	}

	/**
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onInteract(PlayerInteractEvent $event): void {
		if($event->isCancelled() || $event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){ return; }
		$item = $event->getItem();
		if(!$item instanceof ShowcaseItem || !in_array($item->getSpecimen(),['inspector','state_wrench'],true)){ return; }
		$player = $event->getPlayer();
		if(!$player->hasPermission('customiesshowcase.command')){ return; }
		$event->cancel();
		$block = $event->getBlock();
		if($item->getSpecimen() === 'state_wrench' && $block instanceof ShowcaseBlock){
			// Keep multi-block and connection topology owned by their traits.
			foreach(['showcase:powered','showcase:phase','minecraft:sixteen_way_rotation'] as $name){
				$state = $block->getState($name);
				if($state === null){ continue; }
				$values = $state->getValues();
				$index = array_search($state->getCurrentValue(),$values,true);
				$state->setCurrentValue($values[($index+1)%count($values)]);
				$block->getPosition()->getWorld()->setBlock($block->getPosition(),$block);
				break;
			}
		}
		$this->inspectBlock($player,$block);
	}
}
