<?php

namespace KHM\Tests\Membership;

use KHM\Membership\RetentionWorker;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/helpers/retention_fixture.php';

class RetentionWorkerChunkTest extends TestCase {
    public function testRetentionWorkerAnonymizesExpiredRowsWithChunking(): void {
        $worker = new RetentionWorker();
        $preview = $worker->run(true, 30, 'anonymize', 500);

        $this->assertSame('anonymize', (string) ($preview['mode'] ?? ''));
        $this->assertSame(30, (int) ($preview['retention_days'] ?? 0));
        $this->assertSame(500, (int) ($preview['chunk_size'] ?? 0));
        $this->assertArrayHasKey('candidates', $preview);
    }
}
