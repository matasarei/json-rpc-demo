# JSON RPC Symfony Demo
A JSON RPC server demo based on Symfony and the [JsonRPC](https://github.com/matasarei/JsonRPC) lib.
## Quick start

```bash
docker-compose build
docker-compose up -d
```

## Request example
```
[POST] http://localhost:8080/rpc
```

### Hello World
```
{
    "jsonrpc": "2.0", 
    "method": "hello_world",
    "id": 1
}
```
```
{
    "jsonrpc": "2.0",
    "result": "Hello World!",
    "id": 1
}
```

### Concat
```
{
    "jsonrpc": "2.0", 
    "method": "concat",
    "params": ["foo", "bar"],
    "id": 2
}
```
```
{
    "jsonrpc": "2.0",
    "result": "foo bar",
    "id": 2
}
```