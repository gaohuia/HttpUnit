<?php

class InputEndException extends Exception {

}

$blank = [
	"match" => "#^\\s*\n#",
	"action" => "blank"
];

$comment = [
	"match" => "#\/\/.*?$\n#m",
	"action" => "comment",
];

$syntax = [
	"request" => [
		$blank,
		$comment,
		[
			"match" => "#^(GET|POST|PUT|OPTIONS|HEAD|FETCH)\\s*(.*)\n#",
			"action" => "request"
		]
	],
	"options" => [
		$blank,
		$comment,
		[
			"match" => "#^.*\n#",
			"action" => "option"
		]
	],
	"body" => [
		$comment,
		$blank,
		[
			"match" => "#^--(raw|kv)?\n(.*)\n--#s",
			"action" => "body"
		]
	]
];

class Requester {
	private $inputFileName;
	private $fp;
	private $buffer;
	private $context = 'request';
	private $pos = 0;
	private $headers = [];
	private $method = 'GET';
	private $url = '';
	private $body = null;
	private $options = [];
	private $query = [];
	private $config = [];
	private $out = null;

	public function __construct($inputFileName, $projectPath)
	{
		$this->inputFileName = $inputFileName;
		$this->fp = fopen($this->inputFileName, "r");
		$this->out = $inputFileName . '.out.txt';

		if (empty($this->fp)) {
			throw new Exception("Unable to open input file: {$inputFileName}");
		}

		$configFile = $projectPath . '/requester.json';
		if (file_exists($configFile)) {
			$config = json_decode(file_get_contents($configFile), true);
			$this->config = $config;
		}
	}

	public function __destruct()
	{
		if (is_resource($this->fp)) {
			fclose($this->fp);
		}
	}

	private function read()
	{
		if (!feof($this->fp)) {
			$this->buffer .= fread($this->fp, 1024);
		} else {
			throw new InputEndException();
		}
	}

	private function take($length)
	{
		$ret = substr($this->buffer, 0, $length);
		$this->buffer = substr($this->buffer, $length);
		$this->pos += $length;
		return $ret;
	}

	private function readLine()
	{
		while (false === $pos = strpos($this->buffer, "\n")) {
			$this->read();
		}
		$line = $this->take($pos+1);
		return $line;
	}

	public function blank($match)
	{
	}

	public function comment($match)
	{
	}

	public function request($match)
	{
		$this->method = $match[1];
		$this->url = $match[2];

		$pattern = "#(http|https)://#i";
		if (!preg_match($pattern, $this->url)) {
			if (isset($this->config['baseUrl'])) {
				$this->url = $this->config['baseUrl'] . $this->url;
			}
		}

		$this->context = "options";
	}

	public function option($match)
	{
		$line = rtrim($match[0], " \n");
		if (empty($line)) {
			return ;
		}

		$delimiter = ':';
		$colon = strpos($line, ":");
		$equal = strpos($line, '=');

		if ($colon === false || ($equal != false && $equal < $colon)) {
			$delimiter = "=";
		}


		$segments = explode($delimiter, $line, 2);

		if (count($segments) <= 1) {
			$this->context = "body";
			return false;
		}

		$key = $segments[0];
		$value = trim($segments[1]);

		if ($delimiter == '=') {
			if (substr($key, 0, 1) == '@') {
				$key = substr($key, 1);
				$this->query[$key] = $value;
				return ;
			} else {
				switch ($key) {
					case 'timeout':
						$this->options[CURLOPT_TIMEOUT_MS] = intval($value);
						break;
					case 'out':
						$this->out = $value;
						break;
					default:
						break;
				}

				return ;
			}
		}

		$this->headers[] = "{$key}: {$value}";
	}

	public function body($match)
	{
		$bodyType = $match[1];
		$body = trim($match[2], " \r\n");

		switch ($bodyType) {
			case 'kv':
			case '':
				$data = [];
				$rows = explode("\n", $body);
				foreach ($rows as $row) {
					if (empty(trim($row))) {
						continue;
					}

					$kv = explode(":", $row, 2);
					$data[$kv[0]] = trim($kv[1] ?? '');
				}
				$this->body = http_build_query($data);
				break;
			case 'raw':
			default:
				$this->body = $body;
				break;
		}
	}

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


		if ($this->method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}

		if (!empty($this->body)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
		}

		if (!empty($this->headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
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

		$out = "";

		$out .= $headerOut;

		$header = substr($result, 0, $headerSize);
		$body = substr($result, $headerSize);
		
		$out .= $header;


		$bodyJson = json_decode($body);
		if ($bodyJson != null) {
			$body = json_encode($bodyJson, JSON_PRETTY_PRINT);
		}

		$out .= $body;
		$out .= "\n";

		echo $out;

		if (!empty($this->out)) {
			file_put_contents($this->out, $out);
		}
	}

	public function run()
	{
		global $syntax;
		try {
			while (true) {
				$pos1 = $this->pos;
				foreach ($syntax[$this->context] as $rule) {
					if (preg_match($rule['match'], $this->buffer, $match)) {
						$consume = $this->{$rule['action']}($match);
						if ($consume !== false) {
							$this->take(strlen($match[0]));
						} else {
							continue 2;
						}
					}

				}
				$pos2 = $this->pos;

				if ($pos1 == $pos2) {
					$this->read();
				}
			}
		} catch (InputEndException $e) {
			$this->exec();
			return;
		}
	}
}

$input = $argv[1];
$projectPath = $argv[2];

$instance = new Requester($input, $projectPath);
$instance->run();
