<?php
declare(strict_types=1);

namespace customiesdevs\customies;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\network\RuntimeCompatibility;
use customiesdevs\customies\util\RegistrationGuard;
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
			$this->getLogger()->warning('Registry contracts match, but this protocol has not been regression-tested. Use tools/doctor.php against the matching host source when reporting problems.');
		}
		$this->getServer()->getPluginManager()->registerEvents(new CustomiesListener(), $this);

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (): void {
			// This task is scheduled with a 0-tick delay so it runs as soon as the server has started. Plugins should
			// register their custom blocks and entities in onEnable() before this is executed
			CustomiesBlockFactory::getInstance()->addWorkerInitHook();
			RegistrationGuard::freeze();
		}), 0);
	}

}
