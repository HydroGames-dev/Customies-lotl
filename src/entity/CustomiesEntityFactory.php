<?php
declare(strict_types=1);

namespace customiesdevs\customies\entity;

use customiesdevs\customies\util\RegistrationGuard;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use Closure;

final class CustomiesEntityFactory {
	use SingletonTrait;
	/** @var list<string> */
	private array $identifiers = [];

	/** @return list<string> */
	public function getRegisteredIdentifiers(): array { return $this->identifiers; }

	/**
	 * Register an entity to the EntityFactory and all the required mappings. An optional behaviour identifier can be
	 * provided if you want to have your entity behave like a vanilla entity.
	 * @phpstan-param class-string<Entity> $className
	 * @phpstan-param Closure(World $world, CompoundTag $nbt) : Entity $creationFunc
	 */
	public function registerEntity(string $className, string $identifier, ?Closure $creationFunc = null, string $behaviourId = ""): void {
		RegistrationGuard::assertOpen($identifier);
		RegistrationGuard::validateIdentifier($identifier);
		if(in_array($identifier, $this->identifiers, true)){
			throw new \InvalidArgumentException("Entity already registered: $identifier");
		}
		EntityFactory::getInstance()->register($className, $creationFunc ?? static function (World $world, CompoundTag $nbt) use ($className): Entity {
			return new $className(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, [$identifier]);
		$this->updateStaticPacketCache($identifier, $behaviourId);
		$this->identifiers[] = $identifier;
	}

	/**
	 * Updates the AvailableActorIdentifiersPacket to include the new entity.
	 * @param string $identifier example: "customies:my_entity"
	 * @param string $behaviourId
	 */
	private function updateStaticPacketCache(string $identifier, string $behaviourId): void {
		$instance = StaticPacketCache::getInstance();
		/** @var AvailableActorIdentifiersPacket $packet */
		$packet = $instance->getAvailableActorIdentifiers();
		/** @var CompoundTag $root */
		$root = clone $packet->identifiers->getRoot();
		$list = $root->getListTag("idlist") ?? new ListTag();
		$list->push(CompoundTag::create()
			->setString("id", $identifier)
			->setString("bid", $behaviourId));
		$root->setTag("idlist", $list);
		$packet->identifiers = new CacheableNbt($root);
	}
}
