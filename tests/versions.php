<?php
declare(strict_types=1);
// Bedrock version gates: explicit versions exercise future releases on the current host.
$root = $argv[1] ?? getenv('AXOLOTL_ROOT');
if(!$root || !is_file($root . '/vendor/autoload.php')){ fwrite(STDERR, "Usage: php tests/versions.php /path/to/axolotl-checkout-with-vendor\n"); exit(1); }
require $root . '/vendor/autoload.php';
spl_autoload_register(static function(string $class): void {
	$prefix = 'customiesdevs\\customies\\';
	if(str_starts_with($class, $prefix)){ require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
});
function check(bool $ok, string $message): void { if(!$ok){ throw new RuntimeException($message); } }
function rejectsVersion(Closure $action, string $message): void {
	try { $action(); } catch(InvalidArgumentException){ return; }
	throw new RuntimeException($message);
}

use customiesdevs\customies\block\component\CollisionBoxComponent;
use customiesdevs\customies\network\BedrockVersion;
use customiesdevs\customies\network\RuntimeCompatibility;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

foreach([
	'1.26.60' => '1.26.60',
	'1.26.70.21' => '1.26.70',
	'1.26' => '1.26.0',
	'26.60' => '1.26.60',
	'v26.60' => '1.26.60',
	'1.27.0' => '1.27.0'
] as $input => $expected){
	check(BedrockVersion::normalize($input) === $expected, "normalize($input) must be $expected");
}
foreach(['', 'abc', '1', '1.x.0', '26.60.30', 'v1.26.60', '1.26.60-beta'] as $invalid){
	rejectsVersion(static fn() => BedrockVersion::normalize($invalid), "accepted invalid version \"$invalid\"");
}

check(!BedrockVersion::isAtLeast('1.26.70', '1.26.60'), '26.60 is older than 26.70');
check(BedrockVersion::isAtLeast('1.26.70', '1.26.70.21'), 'preview builds of a release meet its gate');
check(BedrockVersion::isAtLeast('1.26.70', '1.26.100'), 'minor versions compare numerically, not as text');
check(BedrockVersion::isAtLeast('1.26.70', '1.27.0'), 'later releases keep earlier gates');
check(BedrockVersion::isAtLeast('1.26.60', '26.60'), 'display versions compare like network versions');

check(BedrockVersion::getCollisionBoxLimit('1.26.60') === 16, '26.60 allows sixteen collision boxes');
check(BedrockVersion::getCollisionBoxLimit('1.26.70') === 32, '1.26.70 allows thirty-two collision boxes');
check(BedrockVersion::getCollisionBoxLimit('1.27.0') === 32, 'later releases keep the higher limit');
check(CollisionBoxComponent::MAX_BOXES === 16, 'the public 26.60 constant must stay unchanged for existing callers');
rejectsVersion(static fn() => BedrockVersion::supports('unknown_feature'), 'accepted an unknown feature gate');

foreach(BedrockVersion::FEATURES as $feature => $minimum){
	check(BedrockVersion::normalize($minimum) === $minimum, "feature $feature needs a normalized network version");
}
check(BedrockVersion::host() === ProtocolInfo::MINECRAFT_VERSION_NETWORK, 'gates follow the host network version');
check(CollisionBoxComponent::getMaxBoxes() === BedrockVersion::getCollisionBoxLimit(), 'collision component must use the host gate');
check(RuntimeCompatibility::describe()['bedrock_features'] === BedrockVersion::describe(), 'doctor report must list version gates');
check(BedrockVersion::describe('1.26.70')['collision_box_32']['enabled'] && !BedrockVersion::describe('1.26.60')['collision_box_32']['enabled'], 'describe() reports per-version state');

// Building 32 boxes must work exactly when the host allows it, and fail with the host's version otherwise.
$box = customiesdevs\customies\block\properties\Box::defaultBox();
$max = CollisionBoxComponent::getMaxBoxes();
$full = (new CollisionBoxComponent())->addBoxes(array_fill(0, $max, $box));
try {
	$full->addBox($box);
	throw new RuntimeException('accepted a box beyond the host limit');
} catch(InvalidArgumentException $e){
	check(str_contains($e->getMessage(), (string) $max) && str_contains($e->getMessage(), BedrockVersion::host()), 'limit errors must name the limit and host version');
}

echo 'PASS: Bedrock version gates on host ', BedrockVersion::host(), ' (collision limit ', $max, '); 1.26.70 and later raise it to 32.', PHP_EOL;
