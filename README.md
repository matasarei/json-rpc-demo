# JSON-RPC Symfony Demo

[![CI](https://github.com/matasarei/json-rpc-demo/actions/workflows/ci.yml/badge.svg)](https://github.com/matasarei/json-rpc-demo/actions/workflows/ci.yml)
[![fguillot/json-rpc](https://img.shields.io/packagist/v/fguillot/json-rpc.svg?label=fguillot%2Fjson-rpc)](https://packagist.org/packages/fguillot/json-rpc)
[![json-rpc downloads](https://img.shields.io/packagist/dt/fguillot/json-rpc.svg?label=json-rpc%20downloads)](https://packagist.org/packages/fguillot/json-rpc)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.4-777bb3?logo=php&logoColor=white)](composer.json)
[![Symfony](https://img.shields.io/badge/symfony-7.4%20LTS-black?logo=symfony)](https://symfony.com/releases/7.4)
[![JSON-RPC](https://img.shields.io/badge/JSON--RPC-2.0-blue)](https://www.jsonrpc.org/specification)
[![License](https://img.shields.io/github/license/matasarei/json-rpc-demo.svg)](LICENSE)

A Symfony 7.4 application showing [fguillot/json-rpc](https://github.com/matasarei/json-rpc)
2.0 from both ends: a **server** answering on `POST /rpc`, and a **client** that calls it
the way an application would. Both run in one FrankenPHP container.

Coming from json-rpc 1.x? See the library's
[UPGRADE-2.0.md](https://github.com/matasarei/json-rpc/blob/master/UPGRADE-2.0.md). The 1.x
version of this demo is kept under the [`v1`](https://github.com/matasarei/json-rpc-demo/tree/v1) tag.

## Quick start

```bash
docker compose up -d --build --wait
docker compose exec app bin/console app:rpc:demo
```

The first command builds and starts the server on <http://localhost:8080/rpc>. If port 8080
is taken, prefix the command with `APP_PORT=8088` or any free port. The second command runs
the client through eleven scenarios against that server, checking each answer:

```
1. A call with named parameters
-------------------------------

✔ result: "Hello, Ada!"
...
8. An application error with its own code and data
--------------------------------------------------

✔ JsonRPC\Exception\ResponseException: Division by zero (code 1001), data {"dividend":7}
...
 [OK] Every scenario behaved as expected.
```

Add `-vv` to see each HTTP request the client sends.

## What it shows

| Scenario | json-rpc 2.0 feature | Where |
|---|---|---|
| Procedures declared with `#[AsRpcProcedure('math.add')]` | an explicit allowlist instead of every public method | [`Procedure/`](src/Rpc/Procedure), [`Kernel`](src/Kernel.php) |
| A procedure class is built on its first call | `ProcedureHandler::withInstanceFactory()` bridging the Symfony container | [`ServerFactory`](src/Rpc/ServerFactory.php), [`RpcProcedurePass`](src/Rpc/DependencyInjection/RpcProcedurePass.php) |
| The server inside a controller | `Server::execute(ServerRequest)` returning a `ServerResponse` with body, status and headers | [`RpcController`](src/Controller/RpcController.php) |
| 1–2. Named and positional parameters | `Client::execute()` | [`RpcDemoCommand`](src/Command/RpcDemoCommand.php) |
| 3. A notification, answered with HTTP 204 | `Client::notify()` | [`RpcDemoCommand`](src/Command/RpcDemoCommand.php) |
| 4. A batch, answers matched by id and returned in call order | `Client::batch()` → `BatchBuilder` | [`RpcDemoCommand`](src/Command/RpcDemoCommand.php) |
| 5. A batch with a failing call | `BatchFailedException::getResults()` / `getErrors()` | [`RpcDemoCommand`](src/Command/RpcDemoCommand.php) |
| 6. Unknown procedure | `MethodNotFoundException` (-32601) | [`RpcDemoCommand`](src/Command/RpcDemoCommand.php) |
| 7. `["5", "3"]` to `add(int $a, int $b)` | no type coercion: `InvalidParamsException` (-32602) | [`MathProcedures`](src/Rpc/Procedure/MathProcedures.php) |
| 8. Division by zero | an application error relayed with its code and data: `ResponseException` | [`DivisionByZeroException`](src/Rpc/Exception/DivisionByZeroException.php) |
| 9. A procedure fails with a database password in its message | internal error masking, on by default: the client sees `-32603 Internal error` | [`DiagnosticsProcedures`](src/Rpc/Procedure/DiagnosticsProcedures.php) |
| 10. Wrong password | HTTP Basic authentication: 401 → `AccessDeniedException` | [`ServerFactory`](src/Rpc/ServerFactory.php) |
| 11. `admin.versions` as `demo`, then as `admin` | middleware answering 403 → `AccessDeniedException` | [`AdminOnlyMiddleware`](src/Rpc/Middleware/AdminOnlyMiddleware.php) |
| The client sends through Symfony HttpClient | `Psr18Transport` with any PSR-18 client; redirects never followed | [`RpcClientFactory`](src/Rpc/RpcClientFactory.php), [`framework.yaml`](config/packages/framework.yaml) |

## The server

Procedures are ordinary services. The attribute both publishes a method and names it:

```php
final readonly class MathProcedures
{
    #[AsRpcProcedure('math.add')]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
```

`ServerFactory` builds the `JsonRPC\Server` service with the security settings 2.0 expects you
to choose deliberately: the users from `RPC_USERS`, a batch limit of `RPC_BATCH_LIMIT` (20),
every `MiddlewareInterface` service, and internal error masking left on. The controller passes
the Symfony request in and returns the answer as it is, so 204, 401 and 403 reach the client:

```php
$rpcResponse = $this->server->execute(ServerRequest::fromString($request->getContent(), [
    ...$request->server->all(),
    'PHP_AUTH_USER' => $request->getUser(),
    'PHP_AUTH_PW' => $request->getPassword(),
]));

return new Response($rpcResponse->body, $rpcResponse->statusCode, $rpcResponse->headers);
```

| Procedure | Parameters | Answers |
|---|---|---|
| `greeting.hello` | `name` (string, default `World`) | `"Hello, <name>!"` |
| `math.add` | `a`, `b` (int) | their sum |
| `math.divide` | `dividend`, `divisor` (float) | the quotient, or error `1001` when `divisor` is 0 |
| `diagnostics.log` | `message` (string) | `true`; meant to be sent as a notification |
| `diagnostics.fail` | none | always fails, with `-32603 Internal error` |
| `admin.versions` | none | PHP, Symfony and json-rpc versions; `admin` user only |

## The client

`RpcClientFactory` wraps the scoped `rpc.client` HttpClient service in json-rpc's PSR-18
transport:

```php
$httpClient = (new HttpClient($url, new Psr18Transport(new Psr18Client($rpcClient))))
    ->withLogger($logger);

$client = (new Client($url, $httpClient))->authentication($username, $password);

$client->execute('math.add', [2, 3]); // 5
```

## Calling it with curl

The endpoint only accepts `Content-Type: application/json`; anything else is answered with
415, so a cross-site form cannot reach it with a browser's cached credentials.

```bash
curl -u demo:demo-secret -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","method":"greeting.hello","params":{"name":"Ada"},"id":1}' http://localhost:8080/rpc
```
```json
{"jsonrpc":"2.0","result":"Hello, Ada!","id":1}
```

```bash
curl -u demo:demo-secret -H 'Content-Type: application/json' -d '[{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":1},{"jsonrpc":"2.0","method":"math.divide","params":[1,0],"id":2}]' http://localhost:8080/rpc
```
```json
[{"jsonrpc":"2.0","result":5,"id":1},{"jsonrpc":"2.0","error":{"code":1001,"message":"Division by zero","data":{"dividend":1}},"id":2}]
```

```bash
curl -u demo:demo-secret -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","method":"diagnostics.fail","id":1}' http://localhost:8080/rpc
```
```json
{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal error"},"id":1}
```

## Credentials

`.env` holds two **demo-only** users, `demo` / `demo-secret` and `admin` / `admin-secret`.
`RPC_USERS` is the server's list; `RPC_USERNAME`, `RPC_PASSWORD`, `RPC_ADMIN_USERNAME` and
`RPC_ADMIN_PASSWORD` are what the client tour logs in with. Override them in `.env.local`.
Anything real belongs in [Symfony secrets](https://symfony.com/doc/current/configuration/secrets.html).

## Tests

```bash
docker compose exec app composer check
```

This runs PHP_CodeSniffer (PSR-12 and strict types), PHPStan at max level, and PHPUnit:

- `tests/Unit`: the procedures, the middleware, the compiler pass and `ServerFactory`, including
  that a procedure class is not built until it is called.
- `tests/Functional`: the `/rpc` endpoint over HTTP through the Symfony kernel, covering every
  error code of the specification, the batch limit, 204, 401 and 403.
- `tests/Integration`: the real client talking to the real server in one process. In the test
  environment `rpc.client` is a `MockHttpClient` that hands each request to the kernel, so the
  tour runs without a web server.

CI runs the same checks on PHP 8.4 and 8.5, and also starts the container and runs the client
tour against it.
