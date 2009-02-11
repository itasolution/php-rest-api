<?php
	class RestApi
	{
		protected $username;
		protected $password;
		protected $format = "json";
		protected $cache_life = 3600;
		protected $cache_dir = "cache";
		protected $cache_ext;
		public $debug = false;

		function __construct()
		{
		}
		
		function login($username, $password)
		{
			$this->username = $username;
			$this->password = $password;
		}
		
		function logout()
		{
			$this->username = null;
			$this->password = null;
		}
		
		function setFormat($format)
		{
			$this->format = $format;
		}
		
		function setCache($life, $dir, $ext)
		{
			$this->cache_life = $life;
			$this->cache_dir = $dir;
			$this->cache_ext = $ext;
		}
		
		function request($url, $extra = array(), $force_post = false)
		{
			if (isset($extra['cache_life']))
				$cache_life = $extra['cache_life'];
			else
				$cache_life = $this->cache_life;
				
			if (isset($extra['get']) && is_array($extra['get']) && count($extra['get'] > 0))
			{
				$url .= '?';
				$first = true;
				foreach ($extra['get'] as $param=>$value)
				{
					if (!$first)
						$url .= '&';
					else
						$first = false;
					$url .= urlencode($param) . '=' . urlencode($value);
				}
			}
			
			if (isset($extra['post']) && is_array($extra['post']) && count($extra['post'] > 0))
			{
				$post = "";
				$first = true;
				foreach ($extra['post'] as $param=>$value)
				{
					if (!$first)
						$post .= '&';
					else
						$first = false;
					$post .= urlencode($param) . '=' . urlencode($value);
				}
			}
			else
				$post = false;
			
			$this->cache_dir = rtrim($this->cache_dir, '/');
			if ($post===false && $cache_life && $this->cache_dir && is_dir($this->cache_dir) && is_writable($this->cache_dir))
				$use_cache = true;
			else
				$use_cache = false;
			
			if ($use_cache)
			{
				$cache_file = $this->cache_dir . '/' .  md5($url.'|'.$postargs.'|'.$this->username.'|'.$this->password);
				if ($this->cache_ext)
					$cache_file .= ".{$this->cache_ext}";
				if (file_exists($cache_file) && ($this->cache_life < 0 || filemtime($cache_file) > time()-($this->cache_life)))
					return $this->objectify(file_get_contents($cache_file));
			}
			
			if ($this->debug)
				echo "REQUEST: $url\n";

			$ch = curl_init($url);
			if($post !== false || $force_post)
			{
				if ($this->debug)
					echo "POST: $post\n";
				curl_setopt($ch, CURLOPT_POST, true);
				if ($post)
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	        }

			if(!is_null($this->username) && !is_null($this->password))
			{
				if ($this->debug)
					echo "AUTH: {$this->username}:{$this->password}\n";
				curl_setopt($ch, CURLOPT_USERPWD, $this->username.':'.$this->password);
			}
			
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
			curl_setopt($ch, CURLOPT_TIMEOUT, 8);

	        $response = curl_exec($ch);
        	$info = curl_getinfo($ch);
        	curl_close($ch);
			
			if ($this->debug)
			{
				echo "\nINFO:\n";
				print_r($info);
				echo "\nRESPONSE:\n";
				echo htmlspecialchars($response);
				echo "\n";
			}

			$object = $this->verify($info, $response);
			
			if ($object !== false && !is_null($object))
			{
				if ($use_cache)
				{
					if ($this->debug)
						echo "CACHE: writing to $cache_file\n";
					file_put_contents($cache_file, $response);
				}
				return $object;
			}

			if ($use_cache && file_exists($cache_file))
				return $this->objectify(file_get_contents($cache_file));
			else
				return false;
		}
		
		function verify($info, $response)
		{
			if ($info['http_code'] != 200)
				return false;
			
			if ($response === false)
				return false;

			return $this->objectify($response);
		}

		function objectify($response)
		{
			switch ($this->format)
			{
				case 'json':
					return json_decode($response);
					break;
				case 'xml':
					return simplexml_load_string($response);
					break;
				case 'php':
					return unserialize($response);
				default:
					return $response;
			}
		}
	}
?>