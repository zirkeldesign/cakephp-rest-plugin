<?php
/**
 * View Class for JSON
 *
 * @author Juan Basso
 * @author Jonathan Dalrymple
 * @author kvz
 * @url http://blog.cakephp-brasil.org/2008/09/11/trabalhando-com-json-no-cakephp-12/
 * @licence MIT
 */
class JsonView extends View {
	public $jsonTab = "  ";

	public function render ($action = null, $layout = null, $file = null) {
		if (!array_key_exists('response', $this->viewVars)) {
			trigger_error(
				'viewVar "response" should have been set by Rest component already',
				E_USER_ERROR
			);
			return false;
		}

		return $this->encode($this->viewVars['response']);
	}

	public function encode ($response) {
		return $this->json_format($this->_encode($response));
	}

	/**
	 * PHP version independent json_encode
	 *
	 * Adapted from http://www.php.net/manual/en/function.json-encode.php#82904.
	 * Author: Steve (30-Apr-2008 05:35)
	 *
	 *
	 * @staticvar array $jsonReplaces
	 * @param array $response
	 *
	 * @return string
	 */
	public function _encode ($response) {
		if (function_exists('json_encode')) {
			// PHP 5.2+
			return json_encode($response);
		}

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

	/**
	 * Pretty print JSON
	 * http://www.php.net/manual/en/function.json-encode.php#80339
	 *
	 * @param string $json
	 * 
	 * @return string
	 */
	public function json_format ($json) {
		$new_json     = "";
		$indent_level = 0;
		$in_string    = false;

		$len  = strlen($json);

		for ($c = 0; $c < $len; $c++) {
			$char = $json[$c];
			switch ($char) {
				case '{':
				case '[':
					if (!$in_string) {
						$new_json .= $char . "\n" . str_repeat($this->jsonTab, $indent_level + 1);
						$indent_level++;
					} else {
						$new_json .= $char;
					}
					break;
				case '}':
				case ']':
					if (!$in_string) {
						$indent_level--;
						$new_json .= "\n" . str_repeat($this->jsonTab, $indent_level) . $char;
					} else {
						$new_json .= $char;
					}
					break;
				case ',':
					if (!$in_string) {
						$new_json .= ",\n" . str_repeat($this->jsonTab, $indent_level);
					} else {
						$new_json .= $char;
					}
					break;
				case ':':
					if (!$in_string) {
						$new_json .= ": ";
					} else {
						$new_json .= $char;
					}
					break;
				case '"':
					if ($c > 0 && $json[$c - 1] != '\\') {
						$in_string = !$in_string;
					}
				default:
					$new_json .= $char;
					break;
			}
		}

        // Return true json at all cost
        if (false === json_decode($new_json)) {
            // If we messed up the semantics, return original
            return $json;
        }

		return $new_json;
	}
}