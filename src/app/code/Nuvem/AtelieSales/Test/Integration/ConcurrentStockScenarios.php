<?php
declare(strict_types=1);

final class ConcurrentStockScenarios
{
    private const WORKER_TIMEOUT_SECONDS = 180;

    public static function waitForStart(array $arguments): void
    {
        $index = array_search('--gate', $arguments, true);
        if ($index === false) {
            return;
        }
        $gate = $arguments[$index + 1] ?? '';
        $ready = $arguments[$index + 2] ?? '';
        assertStockScenario(is_dir(dirname($gate)) && $ready !== '', 'Invalid worker synchronization files.');
        touch($ready);
        $deadline = microtime(true) + self::WORKER_TIMEOUT_SECONDS;
        while (!is_file($gate)) {
            assertStockScenario(microtime(true) < $deadline, 'Timed out waiting for concurrent workers.');
            usleep(10000);
        }
    }

    public static function run(array $commands, ?callable $whileRunning = null): array
    {
        $directory = sys_get_temp_dir() . '/f23-workers-' . bin2hex(random_bytes(8));
        assertStockScenario(mkdir($directory, 0700), 'Cannot create worker directory.');
        $workers = [];
        try {
            foreach ($commands as $index => $command) {
                $output = "$directory/output-$index";
                $arguments = array_merge([PHP_BINARY, __DIR__ . '/StockScenarios.php'], $command,
                    ['--gate', "$directory/start", "$directory/ready-$index"]);
                $process = proc_open($arguments, [0 => ['file', '/dev/null', 'r'],
                    1 => ['file', $output, 'w'], 2 => ['file', $output, 'a']], $pipes);
                assertStockScenario(is_resource($process), 'Cannot launch concurrent worker.');
                $workers[] = ['process' => $process, 'output' => $output];
            }
            $deadline = microtime(true) + self::WORKER_TIMEOUT_SECONDS;
            while (count(glob("$directory/ready-*") ?: []) !== count($workers)) {
                foreach ($workers as $worker) {
                    $status = proc_get_status($worker['process']);
                    assertStockScenario($status['running'], 'Worker failed before start: '
                        . file_get_contents($worker['output']));
                }
                assertStockScenario(microtime(true) < $deadline, 'Workers did not become ready.');
                usleep(10000);
            }
            touch("$directory/start");
            if ($whileRunning !== null) {
                $whileRunning($workers);
            }
            $results = [];
            foreach ($workers as $index => $worker) {
                do {
                    $status = proc_get_status($worker['process']);
                    assertStockScenario(microtime(true) < $deadline, 'Concurrent checkout timed out.');
                    if ($status['running']) {
                        usleep(10000);
                    }
                } while ($status['running']);
                $results[] = ['exit_code' => $status['exitcode'], 'output' => file_get_contents($worker['output'])];
                proc_close($worker['process']);
                unset($workers[$index]);
            }
            return $results;
        } finally {
            foreach ($workers as $worker) {
                proc_terminate($worker['process']);
                proc_close($worker['process']);
            }
            foreach (glob("$directory/*") ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
