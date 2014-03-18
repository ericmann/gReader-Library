<?php
/**
  * This is a core PHP class for reading data from and parsing information to
  * the 'unofficial' Google Reader API.
  */

class JDMReader {
	private $_username;
	private $_password;
	private $_readerUrl;
	private $_accountsUrl;
	private $_sid;
	private $_auth;
	private $_token;
	private $_cookie;

	public $loaded;

	public function __construct($username, $password, $readerUrl = 'http://www.google.com/', $accountsUrl = 'https://www.google.com/') {
		if($this->_connect($username, $password, $readerUrl, $accountsUrl)) {
			$this->loaded = true;
		} else {
			$this->_username = null;
			$this->_password = null;
			$this->loaded = false;
		}
	}

	private function _connect($user, $pass, $readerUrl, $accountsUrl) {
		$this->_username = $user;
		$this->_password = $pass;
		$this->_readerUrl = $readerUrl;
		$this->_accountsUrl = $accountsUrl;

		$this->_getToken();
		return $this->_token != null;
	}

	private function _getToken() {
		$this->_getSID();
		$this->_cookie = "SID=" . $this->_sid . "; domain=.google.com; path=/";

		$url = $this->_readerUrl . 'reader/api/0/token';

		$ch = curl_init();
//		curl_setopt($ch, CURLOPT_COOKIE, $this->_cookie);		// This was the old authentication method
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded', 'Authorization: GoogleLogin auth=' . $this->_auth));		// This, apparently, is the new one.
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$this->_token = curl_exec($ch);

		curl_close($ch);
	}

	private function _getSID() {
		$requestUrl = $this->_accountsUrl . 'accounts/ClientLogin?service=reader&Email=' . urlencode($this->_username) . '&Passwd=' . urlencode($this->_password);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $requestUrl);
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = curl_exec($ch);
		curl_close($ch);

		$sidIndex = strpos($data, "SID=")+4;
		$lsidIndex = strpos($data, "LSID=")-5;
		$authIndex = strpos($data, "Auth=")+5;

		$this->_sid = substr($data, $sidIndex, $lsidIndex);
		$this->_auth = substr($data, $authIndex, strlen($data));
	}

	private function _httpGet($requestUrl, $getArgs) {
		$url = sprintf('%1$s?%2$s', $requestUrl, $getArgs);
		$https = strpos($requestUrl, "https://");

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if($https === true) curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
//		curl_setopt($ch, CURLOPT_COOKIE, $this->_cookie);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded', 'Authorization: GoogleLogin auth=' . $this->_auth));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		try {
			$data = curl_exec($ch);
			curl_close($ch);
		} catch(Exception $err) {
			$data = null;
		}
		return $data;
	}

	private function _httpPost($url, $data, $useragent = null) {
		$buf = "";
		$urlComp = parse_url($url);

	    $fp = fsockopen($urlComp['host'], empty($urlComp['port']) ? 80 : $urlComp['port']) or die("Unable to open socket");

	    fputs($fp, 'POST ' . $urlComp['path'] . " HTTP/1.1\r\n");
	    fputs($fp, 'Host: ' . $urlComp['host'] . "\r\n");
	    fputs($fp, "Content-type: application/x-www-form-urlencoded; charset=UTF-8\r\n");
		fputs($fp, "Content-Length: " . strlen($data) . "\r\n");
		fputs($fp, "Authorization: GoogleLogin auth=$this->_auth\r\n");

		fputs($fp, $data."\r\n\r\n");
	    fputs($fp, "Connection: Close\r\n\r\n");

    	while (!feof($fp))
			$buf .= fgets($fp,128);

	    fclose($fp);
	    return $buf;
	}

	/* Public Methods */

	// List all subscriptions
	public function listAll() {
		$gUrl = $this->_readerUrl . 'reader/api/0/stream/contents/user/-/state/com.google/reading-list';
		$args = sprintf('ck=%1$s', time());

		return $this->_httpGet($gUrl, $args);
	}

	// List a particular number of unread posts from the user's reading list
	public function listUnread($limit) {
		$out = '<ul>';
		$gUrl = $this->_readerUrl . 'reader/api/0/stream/contents/user/-/state/com.google/reading-list';
		$args = sprintf('ot=%1$s&r=n&xt=user/-/state/com.google/read&n=%2$s&ck=%3$s&client=GoogleReaderDashboard', time() - (7*24*3600), $limit, time());

		$data = $this->_httpGet($gUrl, $args);

		$decoded_data = json_decode($data, true);
		$feed_items = $decoded_data['items'];

		foreach($feed_items as $article) {
			$out .= "<li>";
			$out .= '<a class="rsswidget grdLink" href="' . $article['alternate'][0]['href'] . '" target="_blank">';
			$out .= '<span class="grd_title">' . $article['title'] . '</span>';
			$out .= '</a>';
			$out .= '<span class="rss-date">' . date('M j, Y', $article['published']) . '</span>';
			$out .= '<div class="rss-summary">';
			if(isset($article['summary']['content']))
				$out .= '<span class="grd_summary">' . $article['summary']['content'] . '</span>';
			if(isset($article['content']['content'])) {
				$splitdata = split('</p>', $article['content']['content']);
				$out .= '<span class="grd_content">' . $splitdata[0] . '[&#x2026;]</p></span>';
			}
			$out .= "</div>";
			$out .= "</li>";
		}
		$out .= "</ul>";
		return $out;
	}

	// Add new subscription
	public function addFeed($feedUrl) {
		$data = sprintf('quickadd=%1$s&T=%2$s', $feedUrl, $this->_token);
		$url = $this->_readerUrl . '/reader/api/0/subscription/quickadd?client=scroll';

		$response = $this->_httpPost($url, $data);

		if($response == null) return false;
		return true;
	}

	public function addLabelToFeed($label, $feedUrl) {
		$data = sprintf('a=user/-/label/%1$s&s=feed/%2$s&ac=edit&T=%3$s', $label, $feedUrl, $this->_token);
		$url = $this->_readerUrl . 'reader/api/0/subscription/edit?client=scroll';

		$response = $this->_httpPost($url, $data);
		if($response == null) return false;
		return true;
	}

	/**
	  * Mark this an item as read
	  *
	  * This method marks an item as read for a certain user.
	  *
	  * @param string $itemId  The item id that can be retrieved from $this->listAll()
	  *
	  * @return boolean
	  */
	public function markAsRead($itemId) {
		$data = sprintf(
			'i=%1$s&T=%2$s&a=%3$s&ac=edit',
			$itemId, $this->_token,'user/-/state/com.google/read'
		);

		$url = $this->_readerUrl . '/reader/api/0/edit-tag?client=-';

		$response = $this->_httpPost($url, $data);
		if($response == null) return false;
		return true;
	}
}
?>