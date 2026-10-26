<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc\Procedure;

use App\Rpc\Procedure\DiagnosticsProcedures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(DiagnosticsProcedures::class)]
final class DiagnosticsProceduresTest extends TestCase
{
    public function testLogsTheClientMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Client says: {message}', ['message' => 'ping']);

        $this->assertTrue((new DiagnosticsProcedures($logger))->log('ping'));
    }

    public function testFailsWithAMessageThatMustNotReachTheClient(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('s3cr3t-db-password');

        (new DiagnosticsProcedures($this->createStub(LoggerInterface::class)))->fail();
    }
}
