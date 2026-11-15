<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 269 $)
*/
	
namespace Opencart\Extension\KaExtensions\System;

/**
	Simplifies http requests. Optional.

	Originally it was created for fetching images from external services but later we extended it
	with features to make api calls.

	@package Service
*/
class Curl {

	protected $lastError = '';
	const MAX_RESOLVE_ATTEMPTS = 3;

	// HTTP status code of the last response of request(), 0 when no response was received
	protected $last_status_code = 0;
	
	protected $last_file_info = array();
	
	/**
	 Returns the last error.

	 @return string
	*/
	public function getLastError() {
		return $this->lastError;
	}

	/**
	 Returns the HTTP status code of the last response received by request() (after redirects), for
	 example 200 or 400. It is 0 when no response was received (connection error, timeout).

	 @return int
	*/
	public function getLastStatusCode() {
		return $this->last_status_code;
	}
	

	/**
	 Parses an http header block.

	 $header — header block of the response.

	 @return array|false False on error. On success an array like:
	 array(
	 	'status' => array('http_version' => ..., 'status_code' => ..., 'reason_phrase' => ...),
	 	'headers' => array('<hdr1>' => value, '<hdr2>' => value)
	 )
	*/
	protected function parseHttpHeader($header) {
	
		if (!preg_match("/^(.*)\s(.*)\s(.*)\x0D\x0A/U", $header, $matches)) {
			return false;
		}

		$status = array(
			'http_version'  => $matches[1],
			'status_code'   => $matches[2],
			'reason_phrase' => $matches[3]
		);
		
		$headers = array();		
		$header_lines = explode("\x0D\x0A", $header);
		
		foreach ($header_lines as $line) {
			$pair        = array();
			$value_start = strpos($line, ': ');
			$name        = substr($line, 0, $value_start);
			$value       = substr($line, $value_start + 2);
						
			$headers[strtolower($name)] = $value;
		}
		
		$result = array(
			'status' => $status,
			'headers' => $headers
		);
		
		return $result;					
	}

	/**
	 Sends an http request and follows up to 5 redirects. The request is sent with POST when $data
	 is not empty.

	 $url — request url.
	 $data — plain text or array of fields.
	 $options — array of possible values:
	 	timeout — request timeout in seconds (8 by default);
	 	user-agent — text for user-agent value;
	 	extra_headers — array of extra headers (name => value);
	 	webauth_username, webauth_password — credentials for the http Basic authorization. They are
	 	sent as they are (the password was url-encoded before 5.1.1.2);
	 	verify_ssl — verify the certificate of the server, true by default. Set it to false only for
	 	servers with self-signed certificates (before 5.1.1.2 the certificate was never verified).

	 The body of an error response (HTTP 4xx, 5xx) is returned too, its status code is available
	 through getLastStatusCode().

	 @return string|null|false Response body; null or false on error (see getLastError()).
	*/
	public function request($url, $data = array(), $options = array()) {

		if (empty($options['timeout'])) {
			$options['timeout'] = 8;
		}
	
		if (!function_exists('curl_init')) {
			trigger_error(__METHOD__ . ": CURL does not exist");
			return false;
		}
	
		$message = null;
		$this->lastError = '';
		$this->last_status_code = 0;
		
		$tmp_url        = $url;
		$redirect_count = 0;
		$parsed_url = parse_url($url);

		while (++$redirect_count <= 5) {
			$headers = '';
			$message = null;
			
			if (preg_match("/^\/\/.*/", $tmp_url)) {
				$tmp_url = "http:" . $tmp_url;
			}
				
			$parsed_tmp_url = parse_url($tmp_url);

			$curl = curl_init($tmp_url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLOPT_TIMEOUT, $options['timeout']);

			// the server certificate is verified unless the caller turns it off explicitly
			$verify_ssl = !isset($options['verify_ssl']) || !empty($options['verify_ssl']);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verify_ssl ? 2 : 0);
			if (!empty($data)) {
				if (is_array($data)) {
					curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
				} else {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				}
			}
				
			// add custom headers to emulate a regular user activity so it will prevent bans
			// by the user-agent string
			//
			$opt_headers = array();

			// no 'Expect: 100-continue' for large POST bodies, otherwise the interim 100 response
			// is parsed instead of the real one
			$opt_headers[] = 'Expect:';

			if (!empty($options['user-agent'])) {
				$opt_headers[] = 'User-Agent: ' . $options['user-agent'];
			}  else {
				$opt_headers[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0";
			}
			$opt_headers[] = "Host: " . $parsed_tmp_url['host'];

			if (preg_match("/\.yimg\.com/i", $parsed_tmp_url['host'])) {
				$opt_headers[] = "Accept-Language: en-US,en;q=0.5";
				$opt_headers[] = "Accept-Encoding: gzip, deflate";
				$opt_headers[] = "DNT: 1";
				$opt_headers[] = "Upgrade-Insecure-Requests: 1";
			}

			if (!empty($options['extra_headers'])) {
				foreach ($options['extra_headers'] as $k => $v) {
					$opt_headers[] = $k . ': ' . $v;
				}
			}

			if (!empty($opt_headers)) {
				curl_setopt($curl, CURLOPT_HTTPHEADER, $opt_headers);
			}

			// specify web authorization
			//
			if (!empty($options['webauth_username']) && !empty($options['webauth_password'])) {
				curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($curl, CURLOPT_USERNAME, $options['webauth_username']);
				curl_setopt($curl, CURLOPT_PASSWORD, $options['webauth_password']);
			}
				
			// use more attempts to resolve host name. Sometimes curl fails at resolving 
			// a valid host name.
			//
			$resolve_attempt = 0;
			while (true) {
				$response = curl_exec($curl);
				
				if ($response === false) {
					
					// curl_error code 6 means 'could not resolve host name'
					//
					if (curl_errno($curl) == 6) {
						if ($resolve_attempt++ < self::MAX_RESOLVE_ATTEMPTS) {
							continue;
						}
					}
					$this->lastError = 'CURL error (' . curl_errno($curl) . '): ' . curl_error($curl);
				}
					
				break;					
			}				
				
			if ($response === false) {
				break;
			}
			
			$msg_start    = strpos($response, "\x0D\x0A\x0D\x0A");
			$header_block = substr($response, 0, $msg_start);
			$headers      = $this->parseHttpHeader($header_block);				
			if (empty($headers)) {
				$this->last_status_code = 0;
				if (strlen($response) > 1000) {
					$this->lastError = 'No headers received. Response size is ' . strlen($response);
				} else {
					$this->lastError = 'No headers received. Response is "' . $response . '"';
				}
				break;
			}

			$message = substr($response, $msg_start+4);
			$this->last_status_code = (int)$headers['status']['status_code'];
			
			if ($headers['status']['status_code'] >= 200 && $headers['status']['status_code'] < 300) {
				break;
				
			} elseif ($headers['status']['status_code'] >= 300 && $headers['status']['status_code'] < 400) {
				$tmp_url = $headers['headers']['location'];
				continue;
			} elseif ($headers['status']['status_code'] == 400 and preg_match("/\.yimg\.com/i", $parsed_url['host'])) {
				$this->lastError = "Status code: 400";
				continue;
			} else {
				$this->lastError = 'Invalid status code: ' . $headers['status']['status_code'];
				break;
			}
		};
		
		return $message;
	}
	
	
	
	/**
	 Downloads a file by url and follows up to 5 redirects. Partial download is supported, see
	 http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html. File name and content type of the
	 last file are available through getLastFileInfo().

	 $url — file url.
	 $from — byte offset to start downloading from.

	 @return string|null File contents; null on error (see getLastError()).
	*/
	public function getFileContentsByUrl($url, $from = 0) {

		$message = null;
		$this->lastError = '';
		
		if ($from == 0) {
			$this->last_file_info = array();
		}

		// add a protocol to the URL
		//
		if (preg_match("/^\/\/.*/", $url)) {
			$url = "http:" . $url;
		}
		
		$parsed_url = parse_url($url);
		
		if (empty($parsed_url['host'])) {
			$this->lastError = 'Incorrect url. Host parameter was not found';
			return $message;
		}
		
		// add an extra parameter for dropbox images
		// // https://www.dropboxforum.com/t5/Dropbox-files-folders/Getting-downloading-link-of-files-in-Dropbox-automatically/td-p/263073
		//
		if (preg_match("/dropbox\.com/i", $parsed_url['host'])) {
			if (empty($parsed_url['query'])) {
				$url = $url . '?raw=1';
			} else {
				$url = $url . '&raw=1';
			}
		}
		
		if (function_exists('curl_init')) {
		
			$tmp_url        = $url;
			$redirect_count = 0;
			
			while (++$redirect_count <= 5) {

				$tmp_url = str_replace(array('%20'), array(' '), $tmp_url);
			    $tmp_url = $this->encodeUrl($tmp_url);
			    $tmp_url = str_replace(array('+'), array('%20'), $tmp_url);
			    
				$headers = '';
				$message = null;

				$parsed_url = parse_url($tmp_url);
				
				if (empty($parsed_url) || empty($parsed_url['host'])) {
					$this->lastError = 'URL cannot be parsed:' . $tmp_url;
					break;
				}
				
				$curl = curl_init($tmp_url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_HEADER, true);
				curl_setopt($curl, CURLOPT_TIMEOUT, 23);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				
				// download the file partially if requested
				if (!empty($from)) {
					$range = $from . '-';
					curl_setopt($curl, CURLOPT_RANGE, $range);
				}
				
				// add custom headers to emulate a regular user activity so it will prevent bans
				// by the user-agent string
				//
				$opt_headers = array();
 				$opt_headers[] = "Host: " . $parsed_url['host'];
				$opt_headers[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0";
				$opt_headers[] = 'Accept: */*;q=0.1';
				$opt_headers[] = 'Accept-Encoding: gzip, deflate, br';
				
				if (preg_match("/\.yimg\.com/i", $parsed_url['host'])) {
					$opt_headers[] = "Accept-Language: en-US,en;q=0.5";
					$opt_headers[] = "DNT: 1";
					$opt_headers[] = "Upgrade-Insecure-Requests: 1";
				}

				// use more attempts to resolve host name. Sometimes curl fails at resolving 
				// a valid host name.
				//
				$resolve_attempt = 0;
				while (true) {
					$response = curl_exec($curl);

					if ($response === false) {
					
						// curl_error code 6 means 'could not resolve host name'
						//
						if (curl_errno($curl) == 6) {
							if ($resolve_attempt++ < self::MAX_RESOLVE_ATTEMPTS) {
								continue;
							}
						}
						$this->lastError = 'CURL error (' . curl_errno($curl) . '): ' . curl_error($curl);
					}
					
					break;					
				}
				
				if ($response === false) {
					break;
				}
				
				$msg_start    = strpos($response, "\x0D\x0A\x0D\x0A");
				$header_block = substr($response, 0, $msg_start);
				$headers      = $this->parseHttpHeader($header_block);

				if (empty($headers)) {
					if (strlen($response) > 1000) {
						$this->lastError = 'No headers received. Response size is ' . strlen($response);
					} else {
						$this->lastError = 'No headers received. Response is "' . $response . '"';
					}
					break;
				}
/*
A sample header received from nginx server which forced the partial download

["status"]=> array(3) { ["http_version"]=> string(8) "HTTP/1.1" ["status_code"]=> string(3) "200" 
["reason_phrase"]=> string(2) "OK" } ["headers"]=> array(13) / [""]=> string(13) "TP/1.1 200 OK" 
["server"]=> string(5) "nginx" ["date"]=> string(29) "Tue, 22 Jun 2021 23:39:57 GMT" ["content-type"]=> string(10) "image/jpeg" 
["content-length"]=> string(5) "47957" ["last-modified"]=> string(29) "Fri, 04 Dec 2020 12:40:55 GMT" 
["connection"]=> string(10) "keep-alive" ["vary"]=> string(15) "Accept-Encoding" 
["etag"]=> string(15) ""5fca2e57-bb55"" ["expires"]=> string(29) "Wed, 22 Jun 2022 23:39:57 GMT" 
["cache-control"]=> string(6) "public" 
["x-frame-options"]=> string(10) "SAMEORIGIN" ["accept-ranges"]=> string(5) "bytes" 

*/				
				if ($headers['status']['status_code'] >= 200 && $headers['status']['status_code'] < 300) {
				
					$message = substr($response, $msg_start+4);
					
					if (!empty($headers['headers']['content-length'])) {						
						if ($headers['status']['status_code'] != 206) {
							$file_length = (int)$headers['headers']['content-length'];
						} else {
							$file_length = $headers['headers']['content-length'];
						}
						
						// if the file was not downloaded fully
						//
						if ($file_length && strlen($message) != $file_length) {
						
							if (!empty($from) || !empty($headers['headers']['accept-ranges'])) {
								// download the rest of the file
								do {
									$partial_message = $this->getFileContentsByUrl($tmp_url, strlen($message));
									if (!empty($partial_message)) {
										$message .= $partial_message;
									} else {
										$this->lastError = 'File could not be downloaded partially';
										$message = '';
									}
								} while (strlen($message) < $file_length);
							
							} else {
								$this->lastError = "File length mismatch";
								$message = '';
							}
						}
					}
					
					// the content might be encoded
					if (!empty($headers['headers']['content-encoding']) && $headers['headers']['content-encoding'] == 'gzip') {
						if (function_exists('gzdecode')) {
							$message = gzdecode($message);
						} else {
							$message = '';
							$this->lastError = "gzdecode function is required to decode incoming images";
						}
					}
					
					if (!empty($headers['headers']['content-disposition'])) {
						$pairs = $this->parseContentDisposition($headers['headers']['content-disposition']);
						if (!empty($pairs['filename'])) {
							$this->last_file_info['filename'] = $pairs['filename'];
						}
					}
					
					if (!empty($headers['headers']['content-type'])) {
						$this->last_file_info['content-type'] = $headers['headers']['content-type'];
					}
					
					break;
					
				} elseif ($headers['status']['status_code'] >= 300 && $headers['status']['status_code'] < 400) {
					$tmp_url = $headers['headers']['location'];

					// the location may be only a path from dropbox like this:
					// location: /s/raw/gm76vh16anbju2m/JARG1%20IMG_2876.JPG 
					//
					$new_parsed_url = parse_url($tmp_url);
					
					if (empty($new_parsed_url['host'])) {
						$tmp_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $tmp_url;
						
					} elseif (empty($new_parsed_url['scheme'])) {
						$tmp_url = $parsed_url['scheme'] . ':' . $tmp_url;
					}
					
					continue;
				} elseif ($headers['status']['status_code'] == 400 and preg_match("/\.yimg\.com/i", $parsed_url['host'])) {
					$this->lastError = "Status code: 400";
					continue;
				} else {
					$this->lastError = 'Invalid status code: ' . $headers['status']['status_code'];
					break;
				}
				
			};
			
		} else {
			if (ini_get('allow_url_fopen')) {
				$message = file_get_contents($url);
			}
		}
		
		return $message;
	}

	
	/**
	 Returns information about the last downloaded file ('filename', 'content-type').

	 @return array
	*/
	public function getLastFileInfo() {
		return $this->last_file_info;
	}
	
	
	/**
	 Encodes an image URL to a url-compliant string.

	 $url — url to encode.

	 @return string
	*/
	function encodeURL($url) {
    	$encodedUrl = preg_replace_callback('/[^\*$:\/@?&=#]+/usD', function ($matches) {
    		$str = $matches[0];
    		
    		// to prevent urlencode to covert '+' to %2b we have to replace pluses with 
    		// spaces in advance which are later properly converted to '+' by urlencode
    		//    		
    		$str = str_replace('+', ' ', $str);
    		$str = urlencode($str);
    		return $str;
    		
    	}, $url);
    	
    	return $encodedUrl;
	}
	
	/**
	 Parses a content disposition string like:
	 attachment;filename="BK914.jpg";filename*=UTF-8''BK914.jpg

	 $str — content disposition string.

	 @return array Name/value pairs.
	*/
	function parseContentDisposition($str) {
	
		$blocks = explode(";", $str);
		
		$pairs = array();
		foreach ($blocks as $block) {
			$values = explode("=", $block);
			
			if (count($values) != 2) {
				continue;
			}
			
			$pairs[$values[0]] = str_replace("\"", '', $values[1]);
		}
	
		return $pairs;	
	}
}