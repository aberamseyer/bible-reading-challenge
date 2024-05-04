<?php
require_once "functions.php";

class MySessionHandler implements SessionHandlerInterface
{
    private BibleReadingChallenge\Database $s_db;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(BibleReadingChallenge\Database $db)
    {
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
      $data = $this->s_db->col("
        SELECT data
        FROM sessions
        WHERE id = '".$this->s_db->esc($id)."'");
      return $data ?: '';
    }

    public function write($id, $data): bool
    {
      $this->s_db->query("
        INSERT INTO sessions (data, id, last_updated)
        VALUES ('".$this->s_db->esc($data)."', '".$this->s_db->esc($id)."', '".date(MySessionHandler::DATE_FORMAT)."')
        ON CONFLICT (id) DO UPDATE
        SET data=excluded.data, last_updated=excluded.last_updated
      ", "");
      return true;
    }

    public function destroy($id): bool
    {
      $this->s_db->query("DELETE FROM sessions WHERE id = '".$this->s_db->esc($id)."'");
      return true;
    }

    public function gc($maxlifetime): int|false
    {
      $rows_deleted = $this->s_db->query("DELETE FROM sessions WHERE last_updated < '".date(MySessionHandler::DATE_FORMAT, time() - $maxlifetime)."'", "num_rows");
      error_log('Cleaned up '.$rows_deleted.' sessions.');
      return true;
    }
}
