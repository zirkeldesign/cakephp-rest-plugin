<?php 
/** 
 * Class of view for JSON 
 * 
 * @author Juan Basso 
 * @url http://blog.cakephp-brasil.org/2008/09/11/trabalhando-com-json-no-cakephp-12/ 
 * @licence MIT 
 */ 
class JsonView extends View { 
	public function render ($action = null, $layout = null, $file = null) {
		if (!isset($this->viewVars['response'])) { 
			return '[]'; //parent::render($action, $layout, $file);
		}
		if (array_key_exists('response', $this->viewVars)) {
			return $this->renderJson($this->viewVars['response']);
		}

		return 'null';
	}

	public function renderJson ($content) {
		if (function_exists('json_encode')) {
			// PHP 5.2+
			$out = json_encode($content);
		} else {
			// For PHP 4 until PHP 5.1
			$out = $this->encode($content);
		}
		return $out;
	}

	// Adapted from http://www.php.net/manual/en/function.json-encode.php#82904. Author: Steve (30-Apr-2008 05:35)
	public function encode ($response) {
		if (is_null($response)) {
			return 'null';
		}
		if ($response === false) {
			return 'false';
		}
		if ($response === true) {
			return 'true';
		}
		if (is_scalar($response)) {
			if (is_float($response)) {
				return floatval(str_replace(",", ".", strval($response)));
			}

			if (is_string($response)) {
				static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $response) . '"';
			} else {
				return $response;
			}
		}
		$isList = true;
		for ($i = 0, reset($response); $i < count($response); $i++, next($response)) {
			if (key($response) !== $i) {
				$isList = false;
				break;
			}
		}
		$result = array();
		if ($isList) {
			foreach ($response as $v) {
				$result[] = $this->encode($v);
			}
			return '[' . join(',', $result) . ']';
		} else {
			foreach ($response as $k => $v) {
				$result[] = $this->encode($k) . ':' . $this->encode($v);
			}
			return '{' . join(',', $result) . '}';
		}
	}
}