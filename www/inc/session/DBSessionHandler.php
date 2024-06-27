<?php

class DBSessionHandler implements SessionHandlerInterface
{
    private \SQLite3 $s_db;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(\SQLite3 $db)
    {
      $db->query("
        CREATE TABLE IF NOT EXISTS sessions(
          id TEXT PRIMARY KEY,
          data TEXT,
          last_updated DATETIME DEFAULT(CURRENT_TIMESTAMP)
        );
      ");
      $this->s_db = $db;
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
      $stmt = $this->s_db->prepare("
        SELECT data
        FROM sessions
        WHERE id = :id");
      $stmt->bindValue(':id', $id, SQLITE3_TEXT);
      $result = $stmt->execute();
      $data = $result->fetchArray(SQLITE3_NUM);
      return $data[0] ?: '';
    }

    public function write($id, $data): bool
    {
      $stmt = $this->s_db->prepare("
        INSERT INTO sessions (data, id, last_updated)
        VALUES (:data, :id, :last_updated)
        ON CONFLICT (id) DO UPDATE
        SET data=excluded.data, last_updated=excluded.last_updated
      ");
      $stmt->bindValue(':id', $id, SQLITE3_TEXT);
      $stmt->bindValue(':data', $data, SQLITE3_TEXT);
      $stmt->bindValue(':last_updated', date(DBSessionHandler::DATE_FORMAT), SQLITE3_TEXT);
      $stmt->execute();
      return true;
    }

    public function destroy($id): bool
    {
      $stmt = $this->s_db->prepare("DELETE FROM sessions WHERE id = :id");
      $stmt->bindValue(':id', $id, SQLITE3_TEXT);
      $stmt->execute();
      return true;
    }

    public function gc($maxlifetime): int|false
    {
      $stmt = $this->s_db->prepare("
        DELETE FROM sessions
        WHERE last_updated < :last_updated");
      $stmt->bindValue(':last_updated', date(DBSessionHandler::DATE_FORMAT, time() - $maxlifetime), SQLITE3_TEXT);
      $stmt->execute();
      return $this->s_db->changes();
    }
}
