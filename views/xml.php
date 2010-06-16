<?php
/**
 * View Class for XML
 *
 * @author Jonathan Dalrymple
 * @author kvz
 */
class XmlView extends View {
	public $response = '';
	
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
	
	protected function _xmlCleanup ($xml, $header = null) {
		// Indentation
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		if (!$doc->loadXML($xml)) {
			trigger_error('Invalid XML: '.$xml, E_USER_ERROR);
		}
		$doc->encoding = 'utf-8';
		$doc->formatOutput = true;

		return $doc->saveXML();
	}

	public function encode ($response) {
		$rootTag = Inflector::tableize($this->params['controller']) . '_response';

		$this->_encode(array($rootTag => $response));

		return $this->_xmlCleanup($this->response);
	}

	protected function _encode ($response) {
		if (!is_array($response)) {
			$this->response .= $response;
			return;
		}
		
		foreach ($response as $key => $val) {
			// starting tag
			if (!is_numeric($key)) {
				$this->response .= sprintf("<%s>", $key);
			}
			// Another array
			if (is_array($val)){
				// Handle non-associative arrays
				if ($this->_numeric($val)) {
					foreach ($val as $item) {
						#$tag = Inflector::singularize($key);
						$tag = 'item';
						
						$this->response .= sprintf("<%s>", $tag);
						
						$this->_encode($item);
						
						$this->response .= sprintf("</%s>", $tag);
					}
				} else {
					$this->_encode($val);
				}
			} elseif(is_string($val)) {
				$this->response .= $val;
			}
			// Draw closing tag
			if (!is_numeric($key)) {
				$this->response .= sprintf("</%s>", $key);
			}
		}
	}
	
	/**
	* Determine if a given array is numerically indexed
	* 
	* @return Boolean True or False depending on the makeup of the array index
	*/
    protected function _numeric ($arr) {
		foreach ($arr as $key => $val) {
			if (!is_numeric($key)) {
				return false;
			}
		}
		return true;
	}
}