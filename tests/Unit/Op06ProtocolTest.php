<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class Op06ProtocolTest extends TestCase
{
    private function syntheticWorker(string $body): array
    {
        require_once dirname(__DIR__, 2).'/scripts/op06_common.php';
        $process = proc_open([PHP_BINARY, '-r', $body], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $this->assertIsResource($process);

        return ['process' => $process, 'pipes' => $pipes, 'pid' => proc_get_status($process)['pid']];
    }

    public function test_native_process_rendezvous_preserves_pid_and_event_order_without_sql(): void
    {
        $worker = $this->syntheticWorker('echo json_encode(["event"=>"ready","pid"=>getmypid()])."\n"; fflush(STDOUT); if(fgets(STDIN)!=="go\n"){exit(1);} echo json_encode(["event"=>"completed","pid"=>getmypid()])."\n";');
        try {
            $ready = \op06_read_event($worker, 'ready');
            $this->assertSame($worker['pid'], $ready['pid']);
            \op06_send_go($worker);
            $this->assertSame('completed', \op06_read_event($worker, 'completed')['event']);
        } finally {
            \op06_close_worker($worker);
        }
    }

    public function test_wrong_pid_event_and_partial_line_are_refused_with_bounded_wait(): void
    {
        foreach (['echo json_encode(["event"=>"ready","pid"=>1])."\n";' => 'WORKER_EVENT_INVALID',
            'echo json_encode(["event"=>"completed","pid"=>getmypid()])."\n";' => 'WORKER_EVENT_INVALID',
            'echo "{"; fflush(STDOUT); fgets(STDIN);' => 'WORKER_EVENT_TIMEOUT'] as $body => $expected) {
            $worker = $this->syntheticWorker($body);
            try {
                \op06_read_event($worker, 'ready', 1);
                $this->fail('Bad protocol must stop.');
            } catch (RuntimeException $e) {
                $this->assertSame($expected, $e->getMessage());
            } finally {
                \op06_close_worker($worker, true);
            }
        }
    }
}
