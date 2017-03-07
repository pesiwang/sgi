<?php
/**
 * Simple Gateway Interface
 * autho: pesiwang
 * date: 2015/12/10	
 *
 * abbr:
 * sgi -- Simple Gateway Interface
 * sgs -- Simple Gateway Service
 * sga -- Simple Gateway Action
 *
 * sgi's responsibility
 * 1: format input
 * 2: run the corresponding sga
 * 3: format output
 */

/*====================================================
 *					sgi exception 
 *====================================================*/
class SgiException extends Exception {
	
}

/*====================================================
 *					sgi input
 *====================================================*/

abstract class SgiInput{
	public function get($name, $default = NULL, $regex = NULL){
		$value = $this->_get($name);

		if(isset($value) && isset($regex)){
			if(false === filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $regex)))) {
				$value = NULL;
			}
		}
		return (!isset($value) && isset($default)) ? $default : $value;
	}

	abstract protected function _get($name);
}

class SgiInputHttp extends SgiInput{
	protected function _get($name){
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] : NULL;
	}
}


/*====================================================
 *					sgi output
 *====================================================*/
abstract class SgiOutput{
	public function __construct(){
		header('p3p:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
		header("Cache-Control: no-cache, must-revalidate, max-age=0");
	}

	public function setcookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false){
		setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	}

	public function cleancookie($name, $path = '/', $domain = ''){
		setcookie($name, '', time() - 3600, $path, $domain);
	}

	abstract public function render(array $outputData);
}

class SgiOutputJson extends SgiOutput{
	public function render(array $outputData){
		header('Content-Type:text/plain;charset=utf-8');
		echo json_encode($outputData);
	}
}

class SgiOutputJsonp extends SgiOutput{
	private $_callback;

	public function __construct($callback){
		$this->_callback = $callback;
	}
	
	public function render(array $outputData){
		header('Content-Type:text/plain;charset=utf-8');
		echo $this->_callback . '(' . json_encode($outputData) . ');';
	}
}

class SgiOutputXml extends SgiOutput{
	public function render(array $outputData){
		header('Content-Type:text/xml;charset=utf-8');
		echo '<?xml version="1.0" encoding="utf-8" ?><doc>', $this->_array2xml($outputData), "</doc>";
	}

	private function _array2xml(Array $array){
		$xml = '';
		foreach($array as $name => $value){
			if(!is_array($value)){
				$xml .= '<item key="' . htmlentities($name) . '">' . htmlentities($value) . '</item>';
			} else {
				$xml .= '<item key="' . htmlentities($name) . '">' . $this->_array2xml($value) . '</item>';
			}
		}
		return $xml;
	}
}

class SgiSmartyConfig {
	public $tplDir;
	public $compileDir;
	public $varName;
	public $leftDelimiter;
	public $rightDelimiter;

	public function __construct($tplDir, $compileDir, $varName = 'doc', $leftDelimiter = '<!--{', $rightDelimiter = '}-->') {
		$this->tplDir		= $tplDir;
		$this->compileDir	= $compileDir;
		$this->varName		= $varName;
		$this->leftDelimiter	= $leftDelimiter;
		$this->rightDelimiter	= $rightDelimiter;
	}
}

class SgiOutputHtml extends SgiOutput{
	private $_tplName;
	private $_smartyConfig;

	public function __construct($tplName, SgiSmartyConfig $sgiSmartyConfig) {
		$this->_tplName				= $tplName;
		$this->_smartyConfig		= $sgiSmartyConfig;
	}

	public function render(array $outputData){
		header('Content-Type:text/html;charset=utf-8');

		$smarty = new Smarty();
		$smarty->template_dir	= $this->_smartyConfig->tplDir;
		$smarty->compile_dir	= $this->_smartyConfig->compileDir;
		$smarty->left_delimiter		= $this->_smartyConfig->leftDelimiter;
		$smarty->right_delimiter	= $this->_smartyConfig->rightDelimiter;
		$smarty->caching = false;
		$smarty->assign($this->_smartyConfig->varName, $outputData);

		$smarty->display($this->_tplName);
	}
}

/*====================================================
 *					sgi mapper 
 *====================================================*/
class SgiMapper {
	private $_sgsDir;
	private $_uri;
	private $_parsed;

	private $_filePath;
	private $_className;
	private $_methodName;

	public function __construct($sgsDir, $uri) {
		$this->_sgsDir	= $sgsDir;	
		$this->_uri		= $uri;
		$this->_parsed	= false;
	}

	public function toFilePath() {
		if( ! $this->_parsed) {
			$this->_parse();
		}			

		return $this->_filePath;
	}

	public function toClassName() {
		if( ! $this->_parsed) {
			$this->_parse();
		}			

		return $this->_className;
	}

	public function toMethodName() {
		if( ! $this->_parsed) {
			$this->_parse();
		}			

		return $this->_methodName;
	}

	private function _parse() {
		
		// step 1. explode the uri
		$uriElementArr  = explode('/', $this->_uri);
		$uriElementCnt    = count($uriElementArr);
		if($uriElementCnt < 2) {
			throw new SgiException('uri format error, uri=['.$this->_uri.']');
		}

		// step 2. parse the method name
		$methodName = str_replace(Sgi::SGI_SUFFIX, '', $uriElementArr[$uriElementCnt - 1]); // remove '.sgi' suffix
		$methodName = ucfirst($methodName); // make first character uppercase
		$methodName = preg_replace_callback('/_([a-z])/', function ($m) {return strtoupper($m[1]);}, $methodName); // make the character after '_' uppercase
		$this->_methodName	= Sgi::SGA_PREFIX . $methodName;

		// step 3. parse the file path and class name
		$filePathCnt = $uriElementCnt - 1;
		$filePath   = '';
		$className  = '';
		for($i = 0; $i < $filePathCnt; ++$i) {
			$filePath .= $uriElementArr[$i];
			$cn = ucfirst($uriElementArr[$i]);
			$cn = preg_replace_callback('/_([a-z])/', function ($m) {return strtoupper($m[1]);}, $cn); // make the character after '_' uppercase
			$className .= $cn;
			if($i + 1 < $filePathCnt) {
				$filePath   .= '/';
				$className  .= '_';
			} else {
				$filePath .= '.php';
			}
		}

		$this->_filePath	= $this->_sgsDir . '/' . $filePath;
		$this->_className	= $className;

		$this->_parsed	= true;
	}
}

/*====================================================
 *					sgi main class 
 *====================================================*/
class Sgi {
	const OF_KEY			= 'of'; // of is short for Output Format
	const INPUT_DATA_KEY	= 'data';
	const CALLBACK_KEY		= 'callback';

	const SGA_PREFIX	= 'SGA_';
	const SGI_SUFFIX	= '.sgi';
	const TPL_SUFFIX	= '.html';

	const OF_HTML	= 'html';
	const OF_JSON	= 'json';
	const OF_XML	= 'xml';
	const OF_JSONP	= 'jsonp';

	private static $_smartyConfig	= NULL;

	public static function setSmartyConfig(SgiSmartyConfig $smartyConfig) {
		self::$_smartyConfig	= $smartyConfig;
	}

	public static function run($sgsDir) {
		$inputObj	= self::_genInputObject();
		$outputObj	= self::_genOutputObject($inputObj);
		$outputData	= self::_execute($sgsDir, $inputObj);

		$outputObj->render($outputData);
	}

	private static function _genInputObject() {
		return new SgiInputHttp();
	}

	private static function _genOutputObject($inputObj) {
		$outputFormat	= $inputObj->get(self::OF_KEY, self::OF_JSON); // default output format is json
		switch($outputFormat){
			case self::OF_XML:
				return new SgiOutputXml();

			case self::OF_HTML:
				if( ! isset(self::$_smartyConfig)) {
					throw new SgiException('sgi output format is html, but smarty config is not set');
				}
				$tplName	= self::_getTplName();
				return new SgiOutputHtml($tplName, self::$_smartyConfig);

			case self::OF_JSONP:
				$callback = self::_getCallback($inputObj);
				return new SgiOutputJsonp($callback);

			case self::OF_JSON:
				return new SgiOutputJson();

			default:
				throw new SgiException('unknown sgi output format, of=[' . $outputFormat . ']');
		}
	}

	private static function _execute($sgsDir, $inputObj) {
		$uri	= self::_getUri();
		$mapper	= new SgiMapper($sgsDir, $uri);

		// step 1. require file
		$filePath	= $mapper->toFilePath();
		if( ! file_exists($filePath)){
			throw new SgiException('sgi file not exists, file=['.$filePath.']');
		}
		require_once $filePath;

		// step 2. new the object of the class
		$className	= $mapper->toClassName();
		if( ! class_exists($className)) {
			throw new SgiException('sgi class not exists, class=['.$className.']');
		}
		$sgsObject	= new $className();

		// step 3. call the action method
		$methodName	= $mapper->toMethodName();
		if( ! method_exists($sgsObject, $methodName)) {
			throw new SgiException('sga method not exists, method=['.$className.'::'.$methodName.']');
		}
		$inputData	= json_decode($inputObj->get(self::INPUT_DATA_KEY), true);
		if(empty($inputData)){
			$inputData	= array();
		}
		$outputData	= $sgsObject->$methodName($inputData);
		if( ! is_array($outputData)) {
			throw new SgiException('sga method return must be array type, method=['.$className.'::'.$methodName.']');
		}

		return $outputData;
	}

	private static function _getUri() {
		$uri	= preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
		$uri    = substr($uri, 1); // remove the first character '/'

		return $uri;
	}

	private static function _getTplName() {
		$uri		= self::_getUri();
		$tplName	= str_replace(self::SGI_SUFFIX, self::TPL_SUFFIX, $uri);

		return $tplName;
	}

	private static function _getCallback($inputObj) {
		$callback	= $inputObj->get(self::CALLBACK_KEY);
		if( ! isset($callback)) {
			throw new SgiException('sgi output format is jsonp, but no callback parameter passed');
		}

		return $callback;
	}
}


