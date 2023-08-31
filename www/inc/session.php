<?php
require_once "functions.php";

class MySessionHandler implements SessionHandlerInterface
{
    private $s_db;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function open($savePath, $sessionName): bool
    {
        $this->s_db = db();
        return true;
    }

    public function close(): bool
    {
      $_SESSION = [];
      return true;
    }

    public function read($id): string|false
    {
      $data = col("
        SELECT data
        FROM sessions
        WHERE id = '".db_esc($id, $this->s_db)."'", $this->s_db);
      return $data ?: '';
    }

    public function write($id, $data): bool
    {
      query("
        INSERT INTO sessions (data, id, last_updated)
        VALUES ('".db_esc($data, $this->s_db)."', '".db_esc($id, $this->s_db)."', '".date(MySessionHandler::DATE_FORMAT)."')
        ON CONFLICT (id) DO UPDATE
        SET data=excluded.data, last_updated=excluded.last_updated
      ", "", $this->s_db);
      return true;
    }

    public function destroy($id): bool
    {
      query("DELETE FROM sessions WHERE id = '".db_esc($id, $this->s_db)."'", $this->s_db);
      return true;
    }

    public function gc($maxlifetime): int|false
    {
      // keep 'em logged in forever:
      $rows_deleted = query("DELETE FROM sessions WHERE last_udpated < ".date(MySessionHandler::DATE_FORMAT, time() - $maxlifetime), "num_rows", $this->s_db);
      error_log('Cleaned up '.$rows_deleted.' sessions.');
      return true;
    }
}
