<?php

class RedisSessionHandler implements SessionHandlerInterface
{
    private Predis\Client $client;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct()
    {
      $this->client = new Predis\Client(null, [ 'prefix' => 'bible-reading-challenge:sessions/' ]);
    }
    
    public function open($savePath, $sessionName): bool
    {
      return true;
    }

    public function close(): bool
    {
      $_SESSION = [];
      return true;
    }

    public function read($id): string|false
    {
      $hgetall = $this->client->hgetall($id);
      return $hgetall ? $hgetall['data'] : '';
    }

    public function write($id, $data): bool
    {
      $this->client->hset($id, "data", $data);
      $this->client->hset($id, "id", $id);
      $this->client->hset($id, "last_updated", date(RedisSessionHandler::DATE_FORMAT));
      $this->client->expire($id, SESSION_LENGTH);
      return true;
    }

    public function destroy($id): bool
    {
      $this->client->del($id);
      return true;
    }

    public function gc($maxlifetime): int|false
    {
      return true;
    }
}
