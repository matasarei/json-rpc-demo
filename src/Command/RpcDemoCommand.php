<?php

declare(strict_types=1);

namespace App\Command;

use App\Rpc\Exception\DivisionByZeroException;
use App\Rpc\RpcClientFactory;
use Closure;
use JsonRPC\Exception\AccessDeniedException;
use JsonRPC\Exception\BatchFailedException;
use JsonRPC\Exception\InvalidParamsException;
use JsonRPC\Exception\MethodNotFoundException;
use JsonRPC\Exception\ResponseException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use UnexpectedValueException;

/**
 * The client side of the demo: calls the server the way an application would,
 * and checks that every answer is the one json-rpc 2.0 promises.
 */
#[AsCommand(
    name: 'app:rpc:demo',
    description: 'Walk through the json-rpc 2.0 client against the demo server',
    help: 'Run with -vv to see each HTTP request the client sends and the status it gets back.',
)]
final readonly class RpcDemoCommand
{
    public function __construct(
        private RpcClientFactory $clients,
        #[Autowire(env: 'RPC_URL')]
        private string $url,
        #[Autowire(env: 'RPC_USERNAME')]
        private string $username,
        #[Autowire(env: 'RPC_PASSWORD')]
        private string $password,
        #[Autowire(env: 'RPC_ADMIN_USERNAME')]
        private string $adminUsername,
        #[Autowire(env: 'RPC_ADMIN_PASSWORD')]
        private string $adminPassword,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title(sprintf('json-rpc 2.0 client → %s', $this->url));

        $failures = 0;

        foreach ($this->scenarios() as $title => $scenario) {
            $io->section($title);

            try {
                $io->writeln(sprintf('<info>✔</info> %s', $scenario()));
            } catch (Throwable $exception) {
                ++$failures;
                $io->writeln(sprintf(
                    '<error>✘</error> %s: %s',
                    $exception::class,
                    $exception->getMessage(),
                ));
            }
        }

        if ($failures > 0) {
            $io->error(sprintf('%d scenario(s) did not behave as expected.', $failures));

            return Command::FAILURE;
        }

        $io->success('Every scenario behaved as expected.');

        return Command::SUCCESS;
    }

    /**
     * Each scenario returns a description of what happened, and throws when the
     * outcome is not the expected one.
     *
     * @return array<string, Closure(): string>
     */
    private function scenarios(): array
    {
        $demo = $this->clients->create($this->username, $this->password);
        $admin = $this->clients->create($this->adminUsername, $this->adminPassword);
        $intruder = $this->clients->create($this->username, 'not-the-password');

        return [
            '1. A call with named parameters' => fn (): string => $this->expectResult(
                $demo->execute('greeting.hello', ['name' => 'Ada']),
                'Hello, Ada!',
            ),
            '2. A call with positional parameters' => fn (): string => $this->expectResult(
                $demo->execute('math.add', [2, 3]),
                5,
            ),
            '3. A notification: no id, no answer, HTTP 204' => function () use ($demo): string {
                $demo->notify('diagnostics.log', ['Hello from app:rpc:demo']);

                return 'sent; nothing to wait for';
            },
            '4. A batch: one request, answers matched to calls by id' => fn (): string => $this->expectResult(
                $demo->batch()
                    ->execute('math.add', [1, 2])
                    ->execute('greeting.hello', ['name' => 'Grace'])
                    ->execute('math.divide', [7, 2])
                    ->notify('diagnostics.log', ['sent within a batch'])
                    ->send(),
                [3, 'Hello, Grace!', 3.5],
            ),
            '5. A batch with a failing call' => fn (): string => $this->expectFailure(
                fn (): mixed => $demo->batch()
                    ->execute('math.add', [1, 1])
                    ->execute('math.add', ['1', '1'])
                    ->execute('greeting.hello')
                    ->send(),
                BatchFailedException::class,
                fn (BatchFailedException $failure): bool => $failure->getResults() === [0 => 2, 2 => 'Hello, World!']
                    && array_keys($failure->getErrors()) === [1]
                    && $failure->getErrors()[1] instanceof InvalidParamsException,
            ),
            '6. An unknown procedure' => fn (): string => $this->expectFailure(
                fn (): mixed => $demo->execute('math.pow', [2, 10]),
                MethodNotFoundException::class,
            ),
            '7. Parameters of the wrong type are not coerced' => fn (): string => $this->expectFailure(
                fn (): mixed => $demo->execute('math.add', ['5', '3']),
                InvalidParamsException::class,
            ),
            '8. An application error with its own code and data' => fn (): string => $this->expectFailure(
                fn (): mixed => $demo->execute('math.divide', [7, 0]),
                ResponseException::class,
                fn (ResponseException $error): bool => $error->getCode() === DivisionByZeroException::CODE
                    && $error->getData() === ['dividend' => 7],
            ),
            '9. An internal error is masked' => fn (): string => $this->expectFailure(
                fn (): mixed => $demo->execute('diagnostics.fail'),
                ResponseException::class,
                fn (ResponseException $error): bool => $error->getCode() === -32603
                    && $error->getMessage() === 'Internal error',
            ),
            '10. Wrong credentials: HTTP 401' => fn (): string => $this->expectFailure(
                fn (): mixed => $intruder->execute('greeting.hello'),
                AccessDeniedException::class,
                fn (AccessDeniedException $denied): bool => str_contains($denied->getMessage(), '401'),
            ),
            '11. Middleware: admin.versions is kept for the admin, HTTP 403 for others' => fn (): string => sprintf(
                "%s\n  as %s: %s",
                $this->expectFailure(
                    fn (): mixed => $demo->execute('admin.versions'),
                    AccessDeniedException::class,
                    fn (AccessDeniedException $denied): bool => str_contains($denied->getMessage(), '403'),
                ),
                $this->adminUsername,
                $this->describe($admin->execute('admin.versions')),
            ),
        ];
    }

    private function expectResult(mixed $result, mixed $expected): string
    {
        if ($result !== $expected) {
            throw new UnexpectedValueException(sprintf(
                'expected %s, got %s',
                $this->describe($expected),
                $this->describe($result),
            ));
        }

        return sprintf('result: %s', $this->describe($result));
    }

    /**
     * @template T of Throwable
     *
     * @param Closure(): mixed $call
     * @param class-string<T> $expected
     * @param (Closure(T): bool)|null $isExpected Further checks on the exception
     */
    private function expectFailure(Closure $call, string $expected, ?Closure $isExpected = null): string
    {
        try {
            $result = $call();
        } catch (Throwable $exception) {
            if (!$exception instanceof $expected || ($isExpected !== null && !$isExpected($exception))) {
                throw new UnexpectedValueException(sprintf(
                    'expected %s, got %s: %s',
                    $expected,
                    $exception::class,
                    $exception->getMessage(),
                ), 0, $exception);
            }

            return sprintf(
                '%s: %s (code %d)%s',
                $exception::class,
                $exception->getMessage(),
                $exception->getCode(),
                $exception instanceof ResponseException && $exception->getData() !== null
                    ? ', data ' . $this->describe($exception->getData())
                    : '',
            );
        }

        throw new UnexpectedValueException(sprintf(
            'expected %s, got the result %s',
            $expected,
            $this->describe($result),
        ));
    }

    private function describe(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
