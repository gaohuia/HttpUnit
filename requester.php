<?php

class InputEndException extends Exception {

}

$newline = "^";
$endline = "(\n|$)";
$headerKey = "[\\w\\-_]+";
$space = "\\s*";

$comment = [
	"match" => "#{$newline}//([^\n]*)\\n#U",
	"action" => "setComment",
];

$blank = [
	"match" => "#{$newline}\n#",
	"action" => "setComment",
];

$syntax = [
	"main" => [
		$blank,
		$comment,
		[
			"match" => "#{$newline}(GET|POST|PUT|OPTIONS|HEAD|FETCH)\\b{$space}(.*){$endline}#",
			"action" => "setRequest",
			"push" => "request",
		],
	],
	"request" => [
		$blank,
		$comment,
		[
			"match" => "#{$newline}@({$headerKey}){$space}={$space}(.*){$endline}#",
			"action" => "setOption",
		],
		[
			"match" => "#{$newline}({$headerKey}){$space}:{$space}(.*){$endline}#",
			"action" => "setHeader",
		],
		[
			"match" => "#{$newline}({$headerKey}){$space}={$space}(.*){$endline}#",
			"action"=> "setQuery",
		],
		[
			"match" => "#{$newline}--raw\n(.*)\n--#s",
			"action" => "setRawBody",
			"pop" => true,
		],
		[
			"match" => "#{$newline}--(kv)?\n#",
			"action" => "startKvBody",
			"push" => "kvBody",
			"pop" => true,
		],
		[
			"pop" => true,
		],
	],
	"kvBody" => [
		$blank,
		$comment,
		[
			"match" => "#{$newline}({$headerKey}){$space}:{$space}(.*){$endline}#",
			"action" => "setKv",
		],
		[
			"match" => "#{$newline}(--|$)(\n|$)#",
			"action" => "setEndKvBody",
			"pop" => true,
		]
	]
];

class Request {
	public $method = 'GET';
	public $url;
	public $headers = [];
	public $options = [];
	public $body = '';
	public $query = [];
	public $error;
	public $errno;
	public $code;
	public $multipart = false;
	public $config = [];

	public function exec()
	{
		$ch = curl_init();

		$url = $this->url;
		if (!empty($this->query)) {
			$url .= strpos($url, '?') === false ? '?' : '&';
			$url .= http_build_query($this->query);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


		if ($this->method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}

		if (!empty($this->body)) {
			if (!$this->multipart && is_array($this->body)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->body));
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
			}
		}

		if (!empty($this->config['user_agent'])) {
			curl_setopt($ch, CURLOPT_USERAGENT, $this->config['user_agent']);
		}

		$headers = $this->config['headers'] ?? [];

		if (!empty($this->headers)) {
			$headers = array_merge($headers, $this->headers);
		}

		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		if (!empty($this->options)) {
			curl_setopt_array($ch, $this->options);
		}

		$result = curl_exec($ch);
		if ($result === false) {
			echo curl_error($ch);
			die;
		}

		$headerOut = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		curl_close($ch);

		$out = '';

		if ($this->config['header_out']) {
			$out .= $headerOut;
		}

		$header = substr($result, 0, $headerSize);
		$body = substr($result, $headerSize);
		
		if ($this->config['header_in']) {
			$out .= $header;
		}

		$bodyJson = json_decode($body);
		if ($bodyJson != null) {
			$body = json_encode($bodyJson, JSON_PRETTY_PRINT);
		}

		$out .= $body;
		$out .= "\n";

		echo $out;

		if (!empty($this->config['save'])) {
			file_put_contents($this->config['save'], $out);
		}
	}
}

class Requester {
	private $inputFileName;
	private $fp;
	private $buffer;
	private $config = [];
	private $out = null;
	private $pos = 0;

	private $defaultConfig = [
		'debug' => false,
		'header_out' => 1,
		'header_in' => 1,
		'save' => null,
		'user_agent' => 'Requester/0.1.0',
		'headers' => [],
	];

	public function __construct($inputFileName, $projectPath)
	{
		$this->buffer = file_get_contents($inputFileName);
		$this->inputFileName = $inputFileName;
		$this->out = $inputFileName . '.out.txt';


		$this->config = $this->defaultConfig;

		$configFile = $projectPath . '/requester.json';
		if (file_exists($configFile)) {
			$config = json_decode(file_get_contents($configFile), true);
			if (is_array($config)) {
				$this->config = array_merge($this->config, $config);
			} else {
				die("Invalid requester.json");
			}
		}
	}

	public function getConfig($key)
	{
		return $this->config[$key];
	}

	public function newRequest()
	{
		if (isset($this->request)) {
			$this->request->exec();
		}

		$this->request = new Request();
		$this->request->config = $this->config;
	}

	public function setRequest($method, $url)
	{
		$this->newRequest();
		$this->request->method = $method;
		$this->request->url = $url;
	}

	public function setHeader($key, $value)
	{
		$this->request->headers[] = "{$key}: {$value}";
	}

	public function setOption($key, $value)
	{
		$key=trim($key);
		switch ($key) {
			case 'timeout':
				$this->request->options[CURLOPT_TIMEOUT_MS] = (int)$value;
				break;
			default:
				if (array_key_exists($key, $this->defaultConfig)) {
					$this->request->config[$key] = $value;
					break;
				}

				throw new Exception("Unknow option: {$key}");
				break;
		}
	}

	public function setComment()
	{

	}

	public function setBlank()
	{

	}

	public function setQuery($key, $value)
	{
		$this->request->query[$key] = $value;
	}

	public function startKvBody()
	{
		$this->request->body = [];
	}

	public function setKv($key, $value)
	{
		if (substr($value, 0, 1) == '@') {
			$value = substr($value, 1);
			$value = new CURLFile($value);
			$this->request->multipart = true;
		}
		$this->request->body[$key] = $value;
	}

	public function setEndKvBody()
	{
	}

	public function setRawBody($body)
	{
		$this->request->body = $body;
	}

	public function run()
	{
		global $syntax;
		$contentLength = strlen($this->buffer);
		$this->pos = 0;

		$stack = ["main"];

		while ($this->pos < $contentLength) {
			if ($this->getConfig('debug')) {
				echo "==Round==\n";
			}

			$context = end($stack);
			foreach ($syntax[$context] as $rule) {
				if (isset($rule['match'])) {
					$takeAction = false;

					if (preg_match($rule['match'], substr($this->buffer, $this->pos), $matches)) {
						$consume = strlen($matches[0]);
						$this->pos += $consume;

						if ($this->getConfig('debug')) {
							echo "---------------------------INPUT-----------------\n";
							echo $matches[0];
							echo "---------------------------END-------------------\n";
							echo "Call: {$rule['action']} " . implode(',', array_slice($matches, 1));
							echo "\n";
						}

						$takeAction = true;
					}
				} else {
					$takeAction = true;
				}

				if ($takeAction) {
					if (isset($rule['action'])) {
						if (is_callable([$this, $rule['action']])) {
							call_user_func_array([$this, $rule['action']], array_slice($matches, 1));
						} else {
							throw new Exception("Unknow action: {$rule['action']}");
						}
					}

					if (isset($rule['pop'])) {
						array_pop($stack);
					}

					if (isset($rule['push'])) {
						$stack[] = $rule['push'];
					}

					continue 2;
				}
			}

			throw new Exception("Bad Syntax, near: " . substr($this->buffer, $this->pos, 30));
		}

		$this->newRequest();
	}
}

$input = $argv[1];
$projectPath = $argv[2];

$instance = new Requester($input, $projectPath);
$instance->run();
