<?php

class UrlFixer {
	private $is_https;
	private $needle;
	private $preg_needle;
	private $report;
	private $simulation;

	public function __construct () {
		$this->is_https = $this->isHttps();
		$this->needle = $this->getNeedle();
		$this->simulation = true; 

		set_time_limit(0);
	}

	public function run () {
		$this->handleMetastrings();
		$this->handleDescriptions();
	}

	public function simulate () {
		$this->simulation = true;

		$this->handleMetastrings();
		$this->handleDescriptions();

		return $this->report;
	}

	public function setSimulation($value) {
		$this->simulation = $value;
	}

	private function getMetastrings() {
		$db_prefix = elgg_get_config('dbprefix');

		$needle = $this->getNeedle();

		$query = "SELECT * FROM {$db_prefix}metastrings WHERE string LIKE \"%$needle%\"";

		return get_data($query);
	}

	private function getDescriptions() {
		$db_prefix = elgg_get_config('dbprefix');

		$needle = $this->getNeedle();

		$query = "SELECT * FROM {$db_prefix}objects_entity oe
JOIN {$db_prefix}entities e ON e.guid = oe.guid
JOIN {$db_prefix}entity_subtypes es ON e.subtype = es.id
WHERE description LIKE \"%$needle%\"
AND es.subtype IN ('blog', 'page_top', 'page')";

		return get_data($query);
	}

	private function handleMetastrings () {
		$strings = $this->getMetastrings();

		foreach ($strings as $string) {
			$urls = $this->getUrlsFromString($string->string);

			if ($urls) {
				foreach($urls as $url) {
					$new_url = $this->parseUrl($url);

					if ($new_url) {
						if ($this->simulation) {
							$this->report[$url] = array(
								'old' => $url,
								'new' => $new_url
							);
						} else {
							$this->updateMetaString($string, $url, $new_url);
						}
					}
				}
			}
		}
	}

	private function handleDescriptions () {
		$items = $this->getDescriptions();

		foreach ($items as $item) {
			$urls = $this->getUrlsFromString($item->description);

			if ($urls) {
				foreach($urls as $url) {
					$new_url = $this->parseUrl($url);

					if ($new_url) {
						if ($this->simulation) {
							$this->report[$url] = array(
								'old' => $url,
								'new' => $new_url
							);
						} else {
							$this->updateDescription($item, $url, $new_url);
						}
					}
				}
			}
		}
	}

	/**
	 * Get all internal urls from the given string
	 * 
	 * @param string $string The string to check
	 * @return array Array of found urls 
	 */
	function getUrlsFromString($string) {
		$needle = $this->getPregNeedle();

		preg_match_all($needle, $string, $matches);

		return $matches[0];
	}

	/**
	 * Parse deprecated url into new format.
	 * 
	 * @param string $url The url to parse
	 * @param boolean $use_https Is the site using https
	 */
	private function parseUrl ($url) {
		// Change URL scheme if needed
		if ($this->is_https) {
			$url = str_replace('http://', 'https://', $url);
		} else {
			$url = str_replace('https://', 'http://', $url);
		}

		$site_url = elgg_get_site_url();

		// Remove site url so we get the request url 
		$request_url = str_replace($site_url, '', $url);

		$parts = explode("/", $request_url);

		// This part was completely removed in Elgg 1.8
		if ($parts[0] == 'pg') {
			unset($parts[0]);
		}

		// Change 'read' to 'view'
		if ($parts[2] == 'read') {
			$parts[2] = 'view';
		} else {
			// Handle really old urls like
			// http://www.site.com/pg/file/<username>/read/1234/friendly-title
			if ($parts[3] == 'read') {
				$parts[3] = 'view';
				unset($parts[2]);
			}
		}

		// OLD: http://www.example.com/pg/blog/owner/group:1499
		// NEW: http://www.example.com/blog/group/1499/all
		if (strpos($parts[3], 'group:') !== false) {
			// Remove 'owner'
			unset($parts[2]);

			// Change 'group:1499' to 'group/1499'
			$parts[3] = str_replace(':', '/', $parts[3]);

			// Add 'all' to the end
			array_push($parts, 'all');
		} else {
			// Use entity title as friendly title
			if (empty($parts[4])) {
				$parts[4] = $entity->title;
			}
			$parts[4] = elgg_get_friendly_title($parts[4]);
		}

		$new_url =  $site_url . implode('/', $parts);

		// Set the correct URL scheme
		if ($this->is_https) {
			$new_url = str_replace('http://', 'https://', $new_url);
		} else {
			$new_url = str_replace('https://', 'http://', $new_url);
		}

		return $new_url;
	}

	private function updateMetaString ($object, $url, $new_url) {
		$id = $object->id;
		$new_string = str_replace($url, $new_url, $object->string);
		$new_string = sanitise_string($new_string);

		$db_prefix = elgg_get_config('dbprefix');
		$query = "UPDATE {$db_prefix}metastrings SET string = '$new_string' WHERE id = $id";

		return update_data($query);
	}

	private function updateDescription ($object, $url, $new_url) {
		$guid = $object->guid;
		$new_string = str_replace($url, $new_url, $object->description);
		$new_string = sanitise_string($new_string);

		$db_prefix = elgg_get_config('dbprefix');
		$query = "UPDATE {$db_prefix}objects_entity SET description = '$new_string' WHERE guid = $guid";

		return update_data($query);
	}

	private function isHttps() {
		if (strpos(elgg_get_site_url(), 'https') !== false) {
			return true;
		} else {
			return false;
		}
	}

	private function getNeedle() {
		if (isset($this->needle)) {
			return $this->needle;
		}

		$site_url = elgg_get_site_url();
		$part = preg_replace('/http[s]?:\/\//', '', $site_url);
		$this->needle = $part . 'pg/';

		return $this->needle;
	}

	/**
	 * Get needle that is safe to use with regular expressions
	 */
	private function getPregNeedle () {
		if (isset($this->preg_needle)) {
			return $this->preg_needle;
		}

		$site_url = elgg_get_site_url() . 'pg/';
		$needle = preg_quote($site_url, "/");

		$needle = str_replace('http\:', 'http[s]?\:', $needle);
		$needle = str_replace('https\:', 'http[s]?\:', $needle);

		$needle = "/$needle" . '[^<\"\s&]*/';

		$this->preg_needle = $needle;

		return $this->preg_needle;
	}
}
