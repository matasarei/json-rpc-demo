<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc\Middleware;

use App\Rpc\Middleware\AdminOnlyMiddleware;
use JsonRPC\Exception\AccessDeniedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminOnlyMiddleware::class)]
final class AdminOnlyMiddlewareTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testLetsAnyUserCallOrdinaryProcedures(): void
    {
        (new AdminOnlyMiddleware())->execute('demo', 'demo-secret', 'math.add');
    }

    #[DoesNotPerformAssertions]
    public function testLetsTheAdminCallAdminProcedures(): void
    {
        (new AdminOnlyMiddleware())->execute('admin', 'admin-secret', 'admin.versions');
    }

    public function testRefusesAdminProceduresToEveryoneElse(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Procedure "admin.versions" is reserved for the admin user');

        (new AdminOnlyMiddleware())->execute('demo', 'demo-secret', 'admin.versions');
    }

    public function testRefusesAdminProceduresWithoutAUser(): void
    {
        $this->expectException(AccessDeniedException::class);

        (new AdminOnlyMiddleware())->execute(null, null, 'admin.versions');
    }
}
