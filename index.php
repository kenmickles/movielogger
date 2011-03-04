<?php

// convert the config data to constants
$config = parse_ini_file('config.ini');
foreach ( $config as $key => $val ) {
	define(strtoupper($key), $val);
}

// if q is present, we've got something to search for
if ( isset($_REQUEST['q']) ) {
	// extract params
	$search = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : NULL;
	$comment = isset($_REQUEST['comment']) ? trim($_REQUEST['comment']) : NULL;

	// search youtube for a trailer
	$video_id = get_youtube_id($search.' trailer');
	
	// continue if we found a video
	if ( $video_id ) {
		// build the caption for this post, hopefully with a link to the wikipedia article about it
		$caption = get_caption($search, $comment);

		// post the video to tumblr
		$post_id = post_to_tumblr($video_id, $caption);

		// success!
		echo json_encode(array(
			'post_id' => $post_id,
			'success' => 1,
			'message' => 'Video posted successfully!',
		));
	}
	// error out if we couldn't find a video
	else {
		echo json_encode(array(
			'success' => 0,
			'message' => "Couldn't find a trailer on YouTube!",
		));
	}
	
	// we're done
	exit;
}

/**
 * Post a video to Tumblr
 *
 * @param string $video_id 
 * @param string $caption 
 * @return int - The Tumblr post ID
 */
function post_to_tumblr($video_id, $caption) {
	// see http://www.tumblr.com/docs/en/api#api_write for details
	$url = 'http://www.tumblr.com/api/write';
	
	$post_data = array(
		'email' => TUMBLR_EMAIL,
		'password' => TUMBLR_PASSWORD,
		'group' => TUMBLR_BLOG,
		'type' => 'video',
		'embed' => 'http://www.youtube.com/watch?v='.$video_id,
		'caption' => $caption,
		'generator' => "Ken's Movielogger .01",
	);
	
	// make the request
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
	curl_setopt($ch, CURLOPT_HEADER, 0);	
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
	$response = curl_exec($ch);
	curl_close($ch);
	
	return $response;
}

/**
 * Search YouTube for the given query and return the ID of the first result
 *
 * @param string $query 
 * @return string
 */
function get_youtube_id($query) {
	$video_id = NULL;
	
	// build the YT API search URL
	$url = 'http://gdata.youtube.com/feeds/api/videos?'.http_build_query(array(
		'q' => $query,
		'alt' => 'json'
	));
	
	// make the request. NOTE: this must be done via GET
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
	curl_setopt($ch, CURLOPT_HEADER, 0);	
	curl_setopt($ch, CURLOPT_POST, 0);
	$response = curl_exec($ch);
	curl_close($ch);
	
	// decode the response
	$data = json_decode($response, 1);
	
	// if we found anything, use the first video id
	if ( isset($data['feed']['entry'][0]) ) {
		$video_id = str_replace('http://gdata.youtube.com/feeds/api/videos/', '', $data['feed']['entry'][0]['id']['$t']);
	}
	
	return $video_id;
}

/**
 * Build a nice caption for this search string
 *
 * @param string $search 
 * @param string $comment - (optional)
 * @return string
 */
function get_caption($search, $comment=NULL) {
	// if we can find a wikipedia page for this film, use that
	if ( $wiki = get_wikipedia_page($search.' film') ) {
		list($title, $url) = $wiki;
		
		// clean up title
		$title = trim(str_ireplace(array('(Film)', '- Wikipedia, the free encyclopedia', '- Wikipedia'), '', $title));

		// if possible, pull the year out of the search
		$ex = explode(' ', $search);
		$year = $ex[count($ex)-1];

		if ( preg_match('/^\d\d\d\d$/', $year) ) {
			$title .= ' ('.$year.')';
		}

		// nice linked caption
		$caption = '<p><a href="'.$url.'">'.$title.'</a></p>';
	}
	// couldn't find a wiki page, so just use the search text
	else {
		$caption = '<p>'.$search.'</p>';
	}
	
	// if a comment was provided, attach it to the caption
	if ( $comment ) {
		$caption .= '<p>'.nl2br($comment).'</p>';
	}
	
	return $caption;
}

/**
 * Use the Yahoo Search API to find a Wikipedia page for a given search string
 *
 * @param string $query 
 * @return array($title, $url)
 */
function get_wikipedia_page($query) {
	// adding wikipedia to the search should put the main article in the top result
	$query .= ' wikipedia';
	
	$url = 'http://boss.yahooapis.com/ysearch/web/v1/'.urlencode($query).'?appid='.YAHOO_APP_ID.'&format=json';
	
	// make the request
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
	curl_setopt($ch, CURLOPT_HEADER, 0);	
	curl_setopt($ch, CURLOPT_POST, 0);
	$response = curl_exec($ch);
	curl_close($ch);
	
	// decode the response
	$data = json_decode($response, 1);
	
	if ( isset($data['ysearchresponse']['resultset_web'][0]) ) {
		$result = $data['ysearchresponse']['resultset_web'][0];
		return array(strip_tags($result['title']), $result['url']);
	}
	
	return FALSE;
}

?>

<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
		<meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0" /> 
		<title>Movielogger</title>
		<style type="text/css">
			body {
				color: #000;
				background-color: #fff;
				font: 10pt helvetica, arial, sans-serif;
			}
			label {
				display: block;
				line-height: 1.33em;
			}
			label em {
				color: #aaa;
			}
			form input[type="text"], 
			form input[type="email"], 
			form input[type="password"],
			form select,
			form textarea {
				-moz-box-shadow:2px 2px 5px #DDD inset;
				-webkit-box-shadow:2px 2px 5px #DDD inset;
				box-shadow:2px 2px 5px #DDD inset;
				-moz-border-radius: 6px;
				-webkit-border-radius: 6px;
				border-radius: 6px;
				border: 1px solid #999999;
				color: #333333;
				font-size: 1.1em;
				padding: 4px;
				width: 20em;
			}
			form input[type="submit"] {
				font-size: 1.5em;
			}
		</style>
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js" type="text/javascript"></script>
		<script type="text/javascript">	
		$(document).ready(function(){
			// submit the form via ajax
			$('#movie-form').submit(function(){
				
				// validate form
				if ( $('#q').val() == '' ) {
					alert("Please enter the name of a movie!");
					$('#q').focus();
					return false;
				}
				
				var $form = $(this);
				var params = $form.serialize();
				
				// loading...
				var $submit = $form.find('input[type="submit"]');
				var button_text = $submit.val();
				$submit.val('Loading...');
				$form.find(':input').attr('disabled', 'disabled');

				$.post($form.attr('action'), params, function(data){
					// done loading
					$form.find(':input').removeAttr('disabled');
					$submit.val(button_text);
					
					// if it worked, reset the form
					if ( data.success == 1 ) {
						$('#q, #comment').val('');
					}					
					
					// show the message
					alert(data.message);
				}, 'json');

				// disable browser submission
				return false;			
			});			
		});
		</script>
	</head>
	<body>
		<div id="wrapper">
			<form id="movie-form" action="<?php echo htmlentities($_SERVER['PHP_SELF']) ?>" method="post">
				<p>
					<label for="q">Movie: <em>(ex: Back to the future 1985)</em></label>
					<input type="text" name="q" id="q" />
				</p>
				<p>
					<label for="comment">Comment: <em>(optional)</em></label>
					<textarea rows="3" cols="40" name="comment" id="comment"></textarea>
				</p>
				<p>
					<input type="submit" value="Post to Tumblr" />
				</p>
			</form>
		</div>
	</body>
</html>