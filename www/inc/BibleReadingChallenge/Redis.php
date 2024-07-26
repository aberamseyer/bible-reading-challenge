<?php

namespace BibleReadingChallenge;

class Redis {
	private static $instance;
  
	private \Predis\Client $client;
	
	const SITE_NAMESPACE = 'bible-reading-challenge:';
  const LAST_SEEN_KEYSPACE = 'last-seen/';
	const SESSION_KEYSPACE = 'sessions/';
	const CONFIG_KEYSPACE = 'config/';

	private $offline = false;

  private function __construct()
  {
		try {
			$this->client = new \Predis\Client([
				'host' => '127.0.0.1',
				'port' => '6379',
				'timeout' => 1,
				'read_write_timeout' => 1,
				'persistent' => true
			], [ 'prefix' => Redis::SITE_NAMESPACE ]);
			$this->client->connect();
		} catch (\Exception $e) {
			$this->offline = true;
			error_log("redis offline");
		}
  }
  
  private function __clone()
  {
      // Do nothing
  }

  public function __wakeup()
  {
      // Do nothing
			throw new \Exception("Cannot unserialize a singleton.");
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function client(): \Predis\Client
  {
    return $this->client;
  }

	public function is_offline(): bool
	{
		return $this->offline;
	}

	public function get_site_version(): string
	{
		$version = false;
		if ($this->client()) {
			$version = $this->client()->get('config/version');
		}
		if (!$version) {
			$version = trim(`git rev-parse --short HEAD`);
		}
		if ($this->client()) {
			$this->client()->set('config/version', $version);
			$this->client()->expire('config/version', 60);
		}
		return $version;
	}

	public function update_last_seen($id, $time): bool
	{
		if ($this->client()) {
			$this->client->set('last-seen/'.$id, $time);
			return true;
		}
		return false;
	}

	public function user_iterator(): null|\Predis\Collection\Iterator\Keyspace {
		return $this->client()
			? new \Predis\Collection\Iterator\Keyspace(
					$this->client,
					Redis::SITE_NAMESPACE.Redis::LAST_SEEN_KEYSPACE.'*')
			: null;
		
	}
}