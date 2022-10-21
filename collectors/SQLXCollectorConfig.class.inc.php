<?php

/**
 * Class SQLXCollectorConfig
 *
 * @author Sam <samuel.behan@dob.sk>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class SQLXCollectorConfig extends Utils {
	static $config;
	static $collectors;

	public static function loadConfig() {
		$cfg = APPROOT . '/conf/' . 'config.yaml';
		$cfg_local = APPROOT . '/conf/' . 'config-local.yaml';
		if (file_exists($cfg_local))
			$cfg .= ';' . $cfg_local;

		if (isset($_SERVER['SQL_COLLECTOR_CONFIG']))
			$cfg = $_SERVER['SQL_COLLECTOR_CONFIG'];

		$files = explode(';', $cfg);
		$config = array();
		foreach ($files as $file) {
			$data = yaml_parse_file($file);
			if (!$data)
				throw new Exception("Failed to parse file $file");
			$config = array_merge_recursive($config, $data);
		}

		if (isset($_SERVER['SQL_COLLECTOR_LIST']) && $_SERVER['SQL_COLLECTOR_LIST']) {
			$sep = $_SERVER['SQL_COLLECTOR_LIST_SEPARATOR'] ?? "\n";

			$keys = array_keys($config['sources']);
			foreach ($keys as $key) {
				if (isset($config['sources'][$key]['disable']) && $config['sources'][$key]['disable'])
					continue;
				print($key . $sep);
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

	public static function getDefaults() {
		if (isset(static::$config['defaults']))
			return static::$config['defaults'];
		return array();
	}

	public static function getPlaceholders() {
		if (isset(static::$config['placeholders']))
			return static::$config['placeholders'];
		return array();
	}

        public static function getOptionalAttributes() {
		if (isset(static::$config['global']['optional-attributes']))
			return static::$config['global']['optional-attributes'];
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
