<?php
declare(strict_types=1);

// Each pass is a fresh PHP process: no singleton, serializer or cache survives it.
$host = $argv[1] ?? '';
if(!is_file($host . '/vendor/autoload.php')){ throw new InvalidArgumentException('Usage: php tests/restarts.php /path/to/host'); }
$directory = sys_get_temp_dir() . '/customies-restarts-' . bin2hex(random_bytes(8));
mkdir($directory);
try{
	for($cycle = 0; $cycle < 16; ++$cycle){
		$process = proc_open([PHP_BINARY, __DIR__ . '/restart-fixture.php', $host, $directory, (string) $cycle], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		if(!is_resource($process)){ throw new RuntimeException('Could not start restart fixture'); }
		$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]);
		if(proc_close($process) !== 0){ throw new RuntimeException("Restart $cycle failed:\n$output\n$errors"); }
		if(!str_contains($output, 'PASS')){ throw new RuntimeException('Fixture did not finish'); }
	}
	echo "Restart regression passed: 16 fresh processes; changed type IDs/order; saved slots/NBT/states, block-item fallback, stale registry packets and wire inventory round trips.\n";
}finally{
	// Only these exact files are created by this test.
	foreach(['inventory.nbt', 'ids.json'] as $file){ if(is_file($directory . '/' . $file)){ unlink($directory . '/' . $file); } }
	rmdir($directory);
}
