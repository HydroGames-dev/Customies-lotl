<?php
declare(strict_types=1);
// Protocol-aware packet codec helpers. Multi-protocol hosts (NetherGames) add a protocol argument to
// DataPacket::encode()/decode(); single-protocol hosts only encode their current protocol.

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

if(!function_exists('packetWrite')){
	function packetWrite(DataPacket $packet, ?int $protocol = null): string {
		$out = new ByteBufferWriter();
		if((new ReflectionMethod($packet, 'encode'))->getNumberOfParameters() >= 2){
			$packet->encode($out, $protocol ?? ProtocolInfo::CURRENT_PROTOCOL);
		}elseif($protocol !== null && $protocol !== ProtocolInfo::CURRENT_PROTOCOL){
			throw new LogicException("This host only encodes protocol " . ProtocolInfo::CURRENT_PROTOCOL);
		}else{
			$packet->encode($out);
		}
		return $out->getData();
	}

	/** Server-authoritative movement settings in the host library's constructor shape. */
	function testMovementSettings(): pocketmine\network\mcpe\protocol\types\PlayerMovementSettings {
		$class = pocketmine\network\mcpe\protocol\types\PlayerMovementSettings::class;
		$first = (new ReflectionMethod($class, '__construct'))->getParameters()[0];
		return $first->getName() === 'movementType'
			? new $class(pocketmine\network\mcpe\protocol\types\ServerAuthMovementMode::SERVER_AUTHORITATIVE_V3, 20, true)
			: new $class(20, true);
	}

	/** InventoryContentPacket::create() in the host library's shape (multi-protocol hosts add a dynamic container size). */
	function testInventoryContent(int $windowId, array $items, object $containerName, object $storage): pocketmine\network\mcpe\protocol\InventoryContentPacket {
		$class = pocketmine\network\mcpe\protocol\InventoryContentPacket::class;
		return (new ReflectionMethod($class, 'create'))->getNumberOfParameters() >= 5
			? $class::create($windowId, $items, $containerName, 0, $storage)
			: $class::create($windowId, $items, $containerName, $storage);
	}

	/**
	 * Calls a packet's static create() with named values. Parameters a host library adds (NG's multi-version
	 * fields) get neutral defaults: false, 0, '', [] or null.
	 * @param array<string, mixed> $values
	 */
	function testCreate(string $class, array $values): DataPacket {
		$arguments = [];
		foreach((new ReflectionMethod($class, 'create'))->getParameters() as $parameter){
			$name = $parameter->getName();
			if(array_key_exists($name, $values)){ $arguments[] = $values[$name]; continue; }
			if($parameter->isDefaultValueAvailable()){ $arguments[] = $parameter->getDefaultValue(); continue; }
			$type = $parameter->getType();
			$arguments[] = match(true){
				$type?->allowsNull() => null,
				(string) $type === 'bool' => false,
				(string) $type === 'int' => 0,
				(string) $type === 'float' => 0.0,
				(string) $type === 'string' => '',
				(string) $type === 'array' => [],
				default => throw new LogicException("$class::create() needs a test value for \$$name")
			};
		}
		return $class::create(...$arguments);
	}

	/** @template T of DataPacket @param class-string<T> $class @return T */
	function packetRead(string $class, string $bytes, ?int $protocol = null): DataPacket {
		$packet = new $class();
		// NG's StartGamePacket::decodePayload() passes the uninitialized $serverTelemetryData by reference.
		// Servers never decode this clientbound packet; give the round-trip test a placeholder.
		if($packet instanceof pocketmine\network\mcpe\protocol\StartGamePacket && !(new ReflectionProperty($packet, 'serverTelemetryData'))->isInitialized($packet)){
			$packet->serverTelemetryData = new pocketmine\network\mcpe\protocol\types\ServerTelemetryData('', '', '', '');
		}
		$in = new ByteBufferReader($bytes);
		if((new ReflectionMethod($packet, 'decode'))->getNumberOfParameters() >= 2){
			$packet->decode($in, $protocol ?? ProtocolInfo::CURRENT_PROTOCOL);
		}else{
			$packet->decode($in);
		}
		return $packet;
	}
}
