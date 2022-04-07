<?php

/**
 * Class SQLXCollector
 *
 * @author Sam <samuel.behan@dob.sk>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
abstract class SQLXCollector extends Collector
{
	protected $name;
	protected $config;
	protected $query;
	protected $data;
	protected $data_idx = -1;
	protected $data_ukey;

	public function __construct() {
		if (preg_match('/SQLX(.+?)Collector/', get_class($this), $matches)) {
			$this->name = strtolower($matches[1]);
		}
		$this->config = SQLXCollectorConfig::getConfig();
		$this->query = SQLXCollectorConfig::getCollectorConfig($this->name);
		SQLXCollectorConfig::configureCore();
		return parent::__construct();
	}

	protected function connectDB() {
		$config = $this->config;

		// dsn
		if (!isset($config['db']['dsn']))
			$config['db']['dsn'] = $config['dsn'];

		// connect
		$dsn_masked = preg_replace('/;password=[^;]+/', '', $config['db']['dsn']);
		Utils::Log(LOG_INFO, sprintf("Connecting to %s.", $dsn_masked));
		$dbh = new PDO($config['db']['dsn'],
				$config['db']['user'] ?? NULL, $config['db']['password'] ?? NULL,
				array(
					PDO::ATTR_TIMEOUT => 15, // in seconds
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
				));
		if (!$dbh)
			throw new Exception("Failed to connect to DB with " . $config['dsn']);
		Utils::Log(LOG_INFO, sprintf("Connected to %s.", $config['db']['dsn']));

		return $dbh;
	}

	protected function loadData($dbh) {
		if (SQLXCollectorConfig::getCollectorCache($this->name))
			return SQLXCollectorConfig::getCollectorCache($this->name);

		// build SQL
		$sql = $this->query['sql'];
		if (!isset($this->config['vars']['SOURCE']))
			$this->config['vars']['SOURCE'] = SQLXCollectorConfig::getName();
		foreach ($this->config['vars'] as $key => $val) {
			//$match = preg_quote("%$key%");
			$match = "%$key%";
			$sql = str_replace($match, $val, $sql);
		}

		Utils::Log(LOG_INFO, sprintf("Loading data for collector %s", $this->name));
		//print("$sql\n");
		$res = $dbh->query($sql);
		if (!$res && $dbh->errorInfo()) {
			print_r($dbh->errorInfo());
			throw new Exception("Query failed with " . $dbh->errorCode());
		}
		$data = $res->fetchAll(PDO::FETCH_ASSOC);

		// count non-empty values
		array_walk($data, function(&$item) {
			$item['__values'] = count(array_filter(array_values($item), function ($v) { return empty($v) ? NULL : $v; }));
		});

		// sort by key, and count of values
		array_multisort(array_column($data, 'primary_key'), SORT_ASC,
			array_column($data, '__values'), SORT_DESC, $data);
		SQLXCollectorConfig::setCollectorCache($this->name, $data);
		return $data;
	}

	public function Prepare() {
		if (!$this->data) {
			$dbh = $this->connectDB();
			$this->data = $this->loadData($dbh);
			// DISCONNECT ???
		}

		$this->data_idx = 0;
		$this->data_ukey = array();
		//if (!$this->data)
		//	return false;

		return true;
	}

	public function Fetch() {
		while ($this->data_idx < count($this->data))
		{
			$row = $this->data[$this->data_idx++];

			if (!isset($this->name))
				return $row;

			$res1 = array();
			$res2 = array();
			foreach ($row as $k => $v) {
				if (strpos($k, '__') === 0)
					continue;

				if (!preg_match('/^(\w+):(\w+)$/', $k, $matches)) {
					$res1[$k] = $v;
					continue;
				}

				if (strtolower($matches[1]) == $this->name) {
					$res2[$matches[2]] = $v;
					continue;
				}
			}
			$res = array_merge($res1, $res2);

			// check null primary key
			if (!isset($res['primary_key']) || strlen($res['primary_key']) == 0)
				continue;

			// unique by primary_key
			if (isset($res['primary_key'])) {
				$primary_key = $res['primary_key'];
				if (isset($this->data_ukey[$primary_key]))
					continue;

				$this->data_ukey[$primary_key] = 1;
			}

			uksort($res, 'static::cmp');
			return $res;
		}
		return false;
	}

	protected static function cmp($a, $b) {
		// preffer primary key
		if ($a == 'primary_key')
			return -0xFFFFFF;
		if ($b == 'primary_key')
			return 0xFFFFFF;
		return strcasecmp($a, $b);
	}

	protected function MustProcessBeforeSynchro() {
		return true;
	}

	protected function InitProcessBeforeSynchro() {
	}

	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex) {
	}

	public function AttributeIsOptional($sAttCode) {
		if (preg_match('/monitoring(ip)?_/', $sAttCode))
			return true;
		return parent::AttributeIsOptional($sAttCode);
	}
}
