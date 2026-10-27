<?php
declare(strict_types=1);

$host = $argv[1] ?? getenv('AXOLOTL_ROOT');
if(!$host || !is_file($host . '/vendor/autoload.php')){
	fwrite(STDERR, "Usage: php tools/doctor.php /path/to/axolotl-checkout-with-vendor\n");
	exit(2);
}
require $host . '/vendor/autoload.php';
spl_autoload_register(static function(string $class): void {
	$prefix = 'customiesdevs\\customies\\';
	if(str_starts_with($class, $prefix)){
		require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	}
});
$report = customiesdevs\customies\util\Diagnostics::report();
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
exit($report['contract_errors'] === [] ? 0 : 1);
