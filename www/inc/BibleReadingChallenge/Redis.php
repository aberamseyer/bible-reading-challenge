<?php

namespace BibleReadingChallenge;

class Redis {
	private static $instance;
  
	private \Predis\Client $client;
	
	const SITE_NAMESPACE = 'bible-reading-challenge:';
	const SESSION_KEYSPACE = 'sessions/';	// session storage, keyed with session ids
	const CONFIG_KEYSPACE = 'config/';		// 	the site config

	// keyed by $user_id's
	CONST STATS_NAMESPACE = 'user-stats/';
  const LAST_SEEN_KEYSPACE = 'last-seen/';
	const WEBSOCKET_NONCE_KEYSPACE = 'websocket-nonce/';

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

	/**
	 * used by php to cache a user's appearance
	 */
	public function update_last_seen($id, $time): \Predis\Response\Status
	{
		return $this->client->set(Redis::LAST_SEEN_KEYSPACE.$id, $time);
	}

	/**
	 * used by a daily cron to update each user's last_seen time
	 */
	public function get_last_seen($id): string|null
	{
		return $this->client->get(Redis::LAST_SEEN_KEYSPACE.$id);
	}

	public function set_websocket_nonce($id, $nonce)
	{
		$key = Redis::WEBSOCKET_NONCE_KEYSPACE.$nonce;
		$this->client->set($key, $id);
		$this->client->expire($key, 10);

		return true;
	}

	public function user_iterator(): null|\Predis\Collection\Iterator\Keyspace {
		return $this->client()
			? new \Predis\Collection\Iterator\Keyspace(
					$this->client,
					Redis::SITE_NAMESPACE.Redis::LAST_SEEN_KEYSPACE.'*')
			: null;
		
	}
}