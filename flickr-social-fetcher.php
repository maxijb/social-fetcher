<?

class FlickrSocialFetcher {
	private $yt_account_options_key = "social-fetcher-fr-account";
	private $apiKey = "c0d8ad7c9a4c01cc229b02a250e01cf2";
	private $url;
	private $items = array();

	function __construct() {
		$this->url = "https://api.flickr.com/services/rest/?method=flickr.people.getPhotos&api_key=".$this->apiKey."&user_id=".get_option( $this->yt_account_options_key, "" )."&format=json&nojsoncallback=1&extras=description,date_taken,url_s,url_l,date_upload&per_page=500";
	}

	function fetchData($nextPageToken) {
		echo "<br>STARTING FLICKR JOB";
		if (empty($nextPageToken)) $nextPageToken = 1;
		$url = $this->url."&page=".$nextPageToken;
		// echo $url;
		// echo file_get_contents($url);
		// echo "endoffile";
		$data = json_decode(file_get_contents($url));
		// print_r($data);
		$this->saveData($data->photos->photo, $nextPageToken, $data->photos->pages);
		// $this->items = array_merge($this->items, $data[items]);
		// $nextToken = empty($data[nextPageToken]) ? "" : $data[nextPageToken];
		// saveData($nextToken);
	}


	function saveData($items, $thisPage, $pages) {

		$complete = true;
		foreach ($items as $item) {
			$cons = "SELECT * FROM wp_postmeta WHERE meta_key = 'fr-id' AND meta_value = '".$item->id."'";
			$result = mysql_query($cons) or die(mysql_error());
			if (mysql_num_rows($result)) {
				$complete = false;
				echo "<br> Aborting: photo already loaded ".$item->id;
				break;
			} else {
				echo "<br> Importing picture ". $item->id;
				$date = gmdate("Y-m-d H:i:s", $item->dateupload);
				$my_post_test = array(
				  'post_title'    =>  $item->title ,
				  'post_content'  => $item->description->_content,
				  'post_status'   => 'publish',
				  'post_author'   => 1,
				  'post_type' => "fr-pic",
				  'post_date' => $date
				  //'post_category' => array( 8,39 )
				);
				$post_id = wp_insert_post( $my_post_test, $wp_error );
				add_post_meta($post_id, 'fr-id', $item->id);
				add_post_meta($post_id, 'small-pic', $item->url_s);
				add_post_meta($post_id, 'big-pic', $item->url_l);
			}
		}
		if ($thisPage < $pages && $complete) {
			$this->fetchData($thisPage + 1);
		} else {
			echo "<br> FLICKR JOB COMPLETED";
		}
	}

}



?>