<?php 
/**
  * This is a core PHP class for reading data from and parsing information to
  * the 'unofficial' Google Reader API.
  */

//Load OAuthSimple OAuth library - https://github.com/alexd3499/oauthsimple (forked from https://github.com/jrconlin/oauthsimple)
require_once '../OAuthLib/OAuthSimple.php';

class GReader {	
	private $_signatures;
	private $_oathObject;
	
	public $loaded;
	
	public function __construct ( $signatures ) {
		$this->_signatures = $signatures;
		$this->_oauthObject = new OAuthSimple();
		
		$this->loaded = true;
	}
	
	private function _httpGet($url, $parameters) {
		$this->_oauthObject->reset();
		$oauth_signatures = $this->_oauthObject->sign(
			array (
				'path'      => $url,
				'parameters'=> $parameters,
				'signatures'=> $this->_signatures
			)
		);
		$url = $oauth_signatures['signed_url'];
		$https = strpos($url, "https://");
        
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if($https === true) curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded; charset=UTF-8'));

		ob_start();
        
		try {
			curl_exec($ch);
			curl_close($ch);
			$data = ob_get_contents();
			ob_end_clean();
		} catch(Exception $err) {
			$data = null;
		}
		return $data;       
	}
	
	private function _httpPost($host, $path, $data, $useragent = null) {
		$buf = "";
		
	    $fp = fsockopen($host, 80) or die("Unable to open socket");

	    fputs($fp, "POST $path HTTP/1.1\r\n");
	    fputs($fp, "Host: $host\r\n");
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
	
	/**
	  * Lists all suubscriptions
	  *
	  * @return json
	  */
	public function listAll() {
		$gUrl = "http://www.google.com/reader/api/0/stream/contents/user/-/state/com.google/reading-list";
		$args = sprintf('ck=%1$s', time());

		return $this->_httpGet($gUrl, $args);
	}
	
	/**
	  * List a number of unread items
	  *
	  * @param int $limit The number of items to fetch
	  *
	  * @return boolean
	  */
	public function listUnread($limit) {
		$gUrl = 'http://www.google.com/reader/api/0/stream/contents/user/-/state/com.google/reading-list';
		$args = sprintf('ot=%1$s&r=n&xt=user/-/state/com.google/read&n=%2$s&ck=%3$s&client=GoogleReaderDashboard', time() - (7*24*3600), $limit, time());
		
		$data = $this->_httpGet($gUrl, $args);
		
		$decoded_data = json_decode($data, true);
		$feed_items = $decoded_data['items'];

		return $feed_items;
	}

	/**
	  * List a number of starred items
	  *
	  * @param int $limit The number of items to fetch
	  *
	  * @return boolean
	  */
	public function listStarred($limit) {
		$gUrl = 'http://www.google.com/reader/api/0/stream/contents/user/-/state/com.google/starred';
		$args = 'n=' . $limit;
		
		$data = $this->_httpGet($gUrl, $args);
		
		$decoded_data = json_decode($data, true);
		$feed_items = $decoded_data['items'];
		
 		return $feed_items;
	}
	
	/**
	  * List a number of shared items
	  *
	  * @param int $limit The number of items to fetch
	  *
	  * @return boolean
	  */
	public function listShared($limit) {
		$gUrl = 'http://www.google.com/reader/api/0/stream/contents/user/-/state/com.google/broadcast';
		$args = 'n=' . $limit;
		
		$data = $this->_httpGet($gUrl, $args);
		
		$decoded_data = json_decode($data, true);
		$feed_items = $decoded_data['items'];
		
		return $feed_items;
	}

	/**
	  * List a number of tagged items
	  *
	  * @param string $tag The tag used in items
	  * @param int $limit The number of items to fetch
	  *
	  * @return boolean
	  */
	public function listTagged($tag , $limit) {
		$gUrl = 'http://www.google.com/reader/api/0/stream/contents/user/-/label/' . $tag;
		$args = 'n=' . $limit;
		
		$data = $this->_httpGet($gUrl, $args);
		
		$decoded_data = json_decode($data, true);
		$feed_items = $decoded_data['items'];
		
		return $feed_items;
	}
	
	/**
	  * Add a new feed
	  *
	  * @param string $feedUrl
	  *
	  * @return boolean
	  */
	public function addFeed($feedUrl) {
		$data = sprintf('quickadd=%1$s&T=%2$s', $feedUrl, $this->_token);
		$path = '/reader/api/0/subscription/quickadd?client=scroll';
		$host = 'www.google.com';

		$response = $this->_httpPost($host, $path, $data);

		if($response == null) return false;
		return true;
	}
	
	/**
	  * Add a label to a feed
	  *
	  * @param string $label
	  * @param string $feedUrl
	  *
	  * @return boolean
	  */
	public function addLabelToFeed($label, $feedUrl) {
		$data = sprintf('a=user/-/label/%1$s&s=feed/%2$s&ac=edit&T=%3$s', $label, $feedUrl, $this->_token);
		$url = 'http://www.google.com/reader/api/0/subscription/edit?client=scroll';
		
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

		$path = '/reader/api/0/edit-tag?client=-';
		$host = 'www.google.com';

		$response = $this->_httpPost($host, $path, $data);
		if($response == null) return false;
		return true;
	}

	/**
	  * Render Reader Items into an html unordered list (ul)
	  *
	  * @param array $items An array of items
	  *
	  * @return string An HTML representation
	  */
	public function render( $items ) {
		$out = "<ul>";
		foreach($items as $article) {
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
}
?>
