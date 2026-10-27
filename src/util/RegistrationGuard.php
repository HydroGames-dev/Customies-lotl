<?php
declare(strict_types=1);

namespace customiesdevs\customies\util;

/** Registration must finish before workers snapshot the palettes and clients join. */
final class RegistrationGuard {

	private static bool $frozen = false;

	public static function freeze(): void { self::$frozen = true; }
	public static function isFrozen(): bool { return self::$frozen; }

	public static function assertOpen(string $identifier): void {
		if(self::$frozen){
			throw new \LogicException("Cannot register '$identifier' after Customies startup. Register in onLoad()/onEnable() and restart the server.");
		}
	}

	public static function validateIdentifier(string $identifier): void {
		if(preg_match('/^[a-z0-9_.-]+:[a-z0-9_\.\/\-]+$/D', $identifier) !== 1 || str_starts_with($identifier, 'minecraft:')){
			throw new \InvalidArgumentException("Invalid custom identifier '$identifier'; use lowercase namespace:name with your own namespace.");
		}
	}
}
