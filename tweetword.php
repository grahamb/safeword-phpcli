#!/usr/bin/php
<?

require_once('safetweeter.php');
$word = get_current_word(true);
$today = date("F j");
$staus = "The lunchtime safeword for $today is " . $word['word'] . " (via @" . $word['addedby'] . ")";
send_message('public', array(
	'status' => $status;
));

?>