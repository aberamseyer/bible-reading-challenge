<?php

namespace BibleReadingChallenge;

class RedisSessionHandler implements \SessionHandlerInterface
{
    private Redis $redis;
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const KEY_NAMESPACE = 'sessions/';

    public function __construct($redis)
    {
      $this->redis = $redis;
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
      $hgetall = $this->redis->client()->hgetall(RedisSessionHandler::KEY_NAMESPACE.$id);
      return $hgetall ? $hgetall['data'] : '';
    }

    public function write($id, $data): bool
    {
      if ($data) { // don't bother saving the session if no one is logged in
        $this->redis->client()->hmset(RedisSessionHandler::KEY_NAMESPACE.$id, [
          "data" => $data,
          "id" => $id,
          "last_updated" => date(RedisSessionHandler::DATE_FORMAT)
        ]);
        $this->redis->client()->expire(RedisSessionHandler::KEY_NAMESPACE.$id, SESSION_LENGTH);
      }
      return true;
    }

    public function destroy($id): bool
    {
      $this->redis->client()->del(RedisSessionHandler::KEY_NAMESPACE.$id);
      return true;
    }

    public function gc($maxlifetime): int|false
    {
      return true;
    }
}
