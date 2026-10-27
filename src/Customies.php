<?php
declare(strict_types=1);

namespace customiesdevs\customies;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\network\RuntimeCompatibility;
use customiesdevs\customies\util\Diagnostics;
use customiesdevs\customies\util\RegistrationGuard;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;

final class Customies extends PluginBase {
	use SingletonTrait;

	public function onLoad(): void {
		RuntimeCompatibility::assertCompatible();
		self::setInstance($this);
	}

	protected function onEnable(): void {
		$runtime = RuntimeCompatibility::describe();
		$this->getLogger()->info("Host protocol {$runtime['protocol']} / {$runtime['minecraft']}: {$runtime['profile']}");
		if(str_starts_with($runtime['profile'], 'unverified')){
			$this->getLogger()->warning('Registry contracts match, but this protocol has not been regression-tested. Use /customies report before reporting problems.');
		}
		$this->getServer()->getPluginManager()->registerEvents(new CustomiesListener(), $this);

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (): void {
			// This task is scheduled with a 0-tick delay so it runs as soon as the server has started. Plugins should
			// register their custom blocks and entities in onEnable() before this is executed
			CustomiesBlockFactory::getInstance()->addWorkerInitHook();
			RegistrationGuard::freeze();
		}), 0);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if($command->getName() !== 'customies'){ return false; }
		if(!$sender->hasPermission('customies.command.status')){ return true; }
		try{
			switch($args[0] ?? 'status'){
				case 'status':
					$report = Diagnostics::report();
					$sender->sendMessage("Customies {$this->getDescription()->getVersion()} | {$report['host']} | Minecraft {$report['minecraft']} (protocol {$report['protocol']})");
					$sender->sendMessage("Blocks: {$report['custom_blocks']} / states: {$report['custom_block_states']} / item entries (including blocks): {$report['custom_item_entries']} / entities: {$report['custom_entities']} | Registration: " . ($report['registry_frozen'] ? 'closed' : 'open'));
					break;
				case 'report':
					$sender->sendMessage(json_encode(Diagnostics::report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
					break;
				case 'inspect':
					if(!isset($args[1])){ return false; }
					$sender->sendMessage(json_encode(Diagnostics::inspect($args[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
					break;
				default: return false;
			}
		}catch(\InvalidArgumentException $e){ $sender->sendMessage($e->getMessage()); }
		return true;
	}
}
