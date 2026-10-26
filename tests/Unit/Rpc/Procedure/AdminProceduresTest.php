<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc\Procedure;

use App\Rpc\Procedure\AdminProcedures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;

#[CoversClass(AdminProcedures::class)]
final class AdminProceduresTest extends TestCase
{
    public function testReportsTheVersionsTheDemoRunsOn(): void
    {
        $versions = (new AdminProcedures())->versions();

        $this->assertSame(PHP_VERSION, $versions['php']);
        $this->assertSame(Kernel::VERSION, $versions['symfony']);
        $this->assertStringStartsWith('v2.', (string) $versions['json_rpc']);
    }
}
