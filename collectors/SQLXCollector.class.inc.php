<?php


class SQLXCollectorConfig extends Utils {
	static $config;
	static $collectors;

	public static function loadConfig() {
		$cfg = APPROOT . '/conf/' . 'config.yaml';

		if (isset($_SERVER['SQL_COLLECTOR_CONFIG']))
			$cfg = $_SERVER['SQL_COLLECTOR_CONFIG'];

		$config = yaml_parse_file($cfg);
		if (!$config)
			throw new Exception("Failed to parse file $cfg");

		if (isset($_SERVER['SQL_COLLECTOR_LIST']) && $_SERVER['SQL_COLLECTOR_LIST']) {
			$keys = array_keys($config['sources']);
			foreach ($keys as $key) {
				if (isset($config['sources'][$key]['disable']) && $config['sources'][$key]['disable'])
					continue;
				print("$key\n");
			}
			exit(0);
		}
		return $config;
	}

	public static function getName() {
		if (!isset(static::$config))
			static::$config = SQLXCollectorConfig::loadConfig();

		if (isset($_SERVER['SQL_COLLECTOR_SOURCE']) && $_SERVER['SQL_COLLECTOR_SOURCE']) {
			$name = $_SERVER['SQL_COLLECTOR_SOURCE'];
		} elseif (count(static::$config['sources']) == 1) {
			// use the one and only defined
			$keys = array_keys(static::$config['sources']);
			$name = $keys[0];
		} else {
			throw new Exception("No source name specified ! (use SQL_COLLECTOR_SOURCE env var)");
		}
		return $name;
	}

	public static function getConfig() {
		$name = static::getName();

		if (!isset(static::$config['sources'][$name]))
			throw new Exception("Collector source '$name' not defined usign env SQL_COLLECTOR_SOURCE.");
		return static::$config['sources'][$name];
	}

	public static function getPlaceholders() {
		if (isset(static::$config['placeholders']))
			static::$config['placeholders'];
		return array();
	}

        public static function getQueries($name) {
		if (!isset(static::$config['queries'][$name]))
			throw new Exception("Collector queries with '$name' doesn't exists.");
		return static::$config['queries'][$name];
        }

	public static function getCollectors() {
		$name = static::getName();
		$config = static::getConfig();

		// use queries by name
		if (is_string($config['queries'])) {
			$config['queries'] = static::getQueries($config['queries']);
		}

		// verify collectors usage
		$collectors = array();
		foreach ($config['queries'] as &$query) {
			foreach ($query['collectors'] as $collector) {
				$collector = strtolower($collector);
				if (in_array($collector, $collectors))
					throw new Exception("Collector '$collector' of source '$name' is defined in multiple queries");
				array_push($collectors, $collector);
				static::$collectors[$collector] = $query;
			}
		}
		return $collectors;
	}

        public static function getCollectorConfig($collector) {
        	if (!isset(static::$collectors[$collector]))
        		throw new Exception("Collector '$collector' not configured");
        	return static::$collectors[$collector];
        }

	public static function getCollectorCache($collector) {
		if (isset(static::$collectors[$collector]) && isset(static::$collectors[$collector]['data'])	)
			return static::$collectors[$collector]['data'];
		return NULL;
	}

	public static function setCollectorCache($collector, $data) {
		static::$collectors[$collector]['data'] = $data;
		return true;
	}

	public function configureCore() {
		// override toolkit core params
		$config = static::getConfig();
		$globalPlaceholders = static::getPlaceholders();

		if (isset($config['placeholders'])) {
			$placeholders = Utils::$oConfig->Get('json_placeholders', array());
			$placeholders = array_merge($placeholders, array(
					'prefix' => static::getName(),
				), $globalPlaceholders, $config['placeholders']);

			Utils::$oConfig->Set('json_placeholders', $placeholders);
		}
		return true;
	}
}

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

		// TODO: sort here, by primary_key and then sort keys by ksort()
		array_multisort(array_column($data, 'primary_key'), SORT_ASC, $data);
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
		if (strpos($sAttCode, 'monitoring_') == 0)
			return true;
		return parent::AttributeIsOptional($sAttCode);
	}
}
