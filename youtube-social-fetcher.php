<?

class YoutubeSocialFetcher {
	private $yt_account_options_key = "social-fetcher-yt-account";
	private $apiKey = "AIzaSyCf3c3Ica2AWircgkTjlqxheiF642V3CRY";
	private $url;
	private $items = array();

	function __construct() {
		$this->url = "https://www.googleapis.com/youtube/v3/search?key=".$this->apiKey."&channelId=".get_option( $this->yt_account_options_key, "" )."&part=snippet,id&order=date&maxResults=50&orderBy=published";
	}

	function fetchData($nextPageToken) {
		echo "<br>STARTING YOUTUBE JOB";
		$url = !empty($nextPageToken) ? $this->url."&pageToken=".$nextPageToken : $this->url;
		// echo $url;
		// echo file_get_contents($url);
		// echo "endoffile";
		$data = json_decode(file_get_contents($url));
		// print_r($data->nextPageToken);
		$this->saveData($data->items, $data->nextPageToken);
		// $this->items = array_merge($this->items, $data[items]);
		// $nextToken = empty($data[nextPageToken]) ? "" : $data[nextPageToken];
		// saveData($nextToken);
	}


	function saveData($items, $token) {

		$complete = true;
		foreach ($items as $item) {
			$result = mysql_query("SELECT * FROM wp_postmeta WHERE meta_key = 'yt-id' AND meta_value = '".$item->id->videoId."'") or die(mysql_error());
			if (mysql_num_rows($result)) {
				$complete = false;
				echo "<br> Aborting: video already loaded ".$item->id->videoId;
				break;
			} else {
				echo "<br> Importing video ". $item->id->videoId."";
				$date = str_replace("T", " ", $item->snippet->publishedAt);
				$date = str_replace(".000Z", '', $date);
				$my_post_test = array(
				  'post_title'    =>  $item->snippet->title ,
				  'post_content'  => $item->snippet->description,
				  'post_status'   => 'publish',
				  'post_author'   => 1,
				  'post_type' => "yt-video",
				  'post_date' => $date
				  //'post_category' => array( 8,39 )
				);
				$post_id = wp_insert_post( $my_post_test, $wp_error );
				add_post_meta($post_id, 'yt-id', $item->id->videoId);
				add_post_meta($post_id, 'small-pic', $item->snippet->thumbnails->default->url);
				add_post_meta($post_id, 'big-pic', $item->snippet->thumbnails->high->url);
			}
		}

		if (!empty($token) && $complete) {
			$this->fetchData($token);
		} else {
			echo "<br> YOUTUBE JOB COMPLETED";
		}
	}

}



?>