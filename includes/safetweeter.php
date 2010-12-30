<?

require_once('./globals.php');
require_once('./oauth_helper.php');

define('DEBUG', false);

function get_dbh() {
    return new PDO('sqlite:' . DBFILE);
}

function get_direct_messages() {
	$url = 'http://api.twitter.com/1/direct_messages.json';
	$messages = array();
	
	$params['oauth_version'] = '1.0';
	$params['oauth_nonce'] = mt_rand();
	$params['oauth_timestamp'] = time();
	$params['oauth_consumer_key'] = OAUTH_CONSUMER_KEY;
	$params['oauth_token'] = OAUTH_ACCESS_TOKEN;
	$params['oauth_signature_method'] = 'HMAC-SHA1';
	$params['oauth_signature'] = oauth_compute_hmac_sig('GET', $url, $params, OAUTH_CONSUMER_SECRET, OAUTH_ACCESS_SECRET);
	$query_parameter_string = oauth_http_build_query($params);
	$request_url = $url . ($query_parameter_string ? ('?' . $query_parameter_string) : '' );
    $response = do_get($request_url, 80);
	if (! empty($response)) {
		list($info, $header, $body) = $response;
		$retarr = $response;
		if ($body) {
			$messages = json_decode($body);
		}
	}
	return $messages;
}

function get_timeline() {
	$url = 'http://api.twitter.com/1/statuses/user_timeline.json_decode';
	$messages = array();
	
	$params['oauth_version'] = '1.0';
	$params['oauth_nonce'] = mt_rand();
	$params['oauth_timestamp'] = time();
	$params['oauth_consumer_key'] = OAUTH_CONSUMER_KEY;
	$params['oauth_token'] = OAUTH_ACCESS_TOKEN;
	$params['oauth_signature_method'] = 'HMAC-SHA1';
	$params['oauth_signature'] = oauth_compute_hmac_sig('GET', $url, $params, OAUTH_CONSUMER_SECRET, OAUTH_ACCESS_SECRET);
	$query_parameter_string = oauth_http_build_query($params);
	$request_url = $url . ($query_parameter_string ? ('?' . $query_parameter_string) : '' );
    $response = do_get($request_url, 80);
	if (! empty($response)) {
		list($info, $header, $body) = $response;
		$retarr = $response;
		if ($body) {
			$messages = json_decode($body);
		}
	}
	return $messages;
}

function send_message($type, $options) {
    $urls = array(
        'dm' => 'http://api.twitter.com/1/direct_messages/new.json',
        'public' => 'http://api.twitter.com/1/statuses/update.json'
    );
    $url = $urls[$type];
    
    foreach ($options as $k => $v) {
        $params[$k] = $v;
    }
    
    $params['lat'] = TWEET_LAT;
    $params['long'] = TWEET_LONG;
    $params['display_coordinates'] = 'true';
    $params['oauth_version'] = '1.0';
	$params['oauth_nonce'] = mt_rand();
	$params['oauth_timestamp'] = time();
	$params['oauth_consumer_key'] = OAUTH_CONSUMER_KEY;
	$params['oauth_token'] = OAUTH_ACCESS_TOKEN;
	$params['oauth_signature_method'] = 'HMAC-SHA1';
	$params['oauth_signature'] = oauth_compute_hmac_sig('POST', $url, $params, OAUTH_CONSUMER_SECRET, OAUTH_ACCESS_SECRET);
	$query_parameter_string = oauth_http_build_query($params, true);
    $header = build_oauth_header($params, "Twitter API");
    $headers[] = $header;
    $request_url = $url;
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $response = do_post($request_url, $query_parameter_string, 80, $headers);
    if (! empty($response)) {
        list($info, $header, $body) = $response;
        if ($body && DEBUG) {
          print(json_pretty_print($body));
        }
    }
}

function delete_message($message_id) {
    $url = "http://api.twitter.com/1/direct_messages/destroy/$message_id.json";
    
    $params['include_entities'] = "false";
    $params['oauth_version'] = '1.0';
	$params['oauth_nonce'] = mt_rand();
	$params['oauth_timestamp'] = time();
	$params['oauth_consumer_key'] = OAUTH_CONSUMER_KEY;
	$params['oauth_token'] = OAUTH_ACCESS_TOKEN;
	$params['oauth_signature_method'] = 'HMAC-SHA1';
	$params['oauth_signature'] = oauth_compute_hmac_sig('POST', $url, $params, OAUTH_CONSUMER_SECRET, OAUTH_ACCESS_SECRET);
	$query_parameter_string = oauth_http_build_query($params, true);
    $header = build_oauth_header($params, "Twitter API");
    $headers[] = $header;
    $request_url = $url;
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $response = do_post($request_url, $query_parameter_string, 80, $headers);
    if (! empty($response)) {
        list($info, $header, $body) = $response;
        if ($body && DEBUG) {
          print(json_pretty_print($body));
        }
    }
    
}

function process_direct_message($message) {
    
    $commands = array(
        "add" => "add",
        "create" => "add",
        "new" => "add",
        "delete" => "delete",
        "del" => "delete",
        "remove" => "delete",
        "rm" => "delete",
        "help" => "help",
        "man" => "help",
		"stats" => "stats",
		"statistics" => "stats",
		"about" => "about"
    );
    
	// get the text, id and sender of the message
	$text = $message->text;
	$user = $message->sender->screen_name;
	$user_id = $message->sender->id_str;
	$message_id = $message->id;
	
	// get the command
	$textarr = split(' ', $text);
	$command = array_shift($textarr);
    $text = array_shift($textarr);
    if (strlen($text) > WORD_LENGTH_LIMIT) {
        tweet_error(array(
	       'error' => 'word_too_long',
	       'user_id' => $user_id,
	       'screen_name' => $user
	    ));
	    delete_message($message_id);
	    return false;	    
    }
    
    if (array_key_exists($command, $commands)) {
	    $command = $commands[$command];
	    exec_command($command, $text, $user, $user_id);
	    delete_message($message_id);
	} else {
	    tweet_error(array(
	       'error' => 'nocmd',
	       'user_id' => $user_id,
	       'screen_name' => $user
	    ));
	    delete_message($message_id);
	}
}

function get_all_words() {
	$words = array();
	$dbh = get_dbh();
	$query = $dbh->prepare("SELECT word from words");
	$query->execute();
	while ($row = $query->fetch()) {
		$words[] = $row['word'];
	}
	return $words;
}

function get_current_word($return_all=false) {
	$dbh = get_dbh();
	$query = $dbh->prepare("SELECT * FROM words WHERE wordoftheday=1");
	$query->execute();
	$word = array();
	while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
		foreach ($row as $k=>$v) {
		    $word[$k] = $v;
		}
	}
	return $return_all ? $word : $word['word'];
}

function word_exists($word) {
	$words = get_all_words();
	return in_array($word, $words);
}

function tweet_error($options) {
    $errors = array(
        'nocmd' => "Oops! You did not specify a command in your tweet:\nTo add a word: \"add [word]\"\nTo delete a word \"del [word]\"\n\nPlease try again.",
        'add_existing_word' => 'Oops! The word you tried to add, %WORD%, already exists. Try another word!',
        'delete_nonexistant_word' => 'Oops! The word you tried to delete, %WORD%, doesn\'t exist. You can always add it!',
        'delete_current_word' => 'Oops! The word you tried to delete, %WORD%, is today\'s current word. Try again tomorrow.',
        'word_too_long' => 'Oops! The word you used is too long. Words can not be longer than 50 characters. Please try again.'
    );

    $error = $options['error'];
    $error_str = $errors[$error];
    if (array_key_exists('word', $options)) {
        $error_str = str_replace("%WORD%", $options['word'], $error_str);
    }

    if (DEBUG) {
        echo $options['screen_name'] . " --> $error_str\n";
    } else {
        send_message('dm', array(
            'text' => $error_str,
            'user_id' => $options['user_id'],
            'screen_name' => $options['screen_name']
        ));
    }
    
}

function exec_command($command, $text, $user, $user_id) {
   
    switch ($command) {
        case 'add':
            if (word_exists($text)) {
                tweet_error(array(
                    'error' => 'add_existing_word',
                    'word' => $text,
                    'screen_name' => $user,
                    'user_id' => $user_id
                ));
            } else {
                if (add_word($text, $user)) {
                    if (DEBUG) {
                        echo "TWEET --> \"$text\" has been added as a new safeword by @$user.";
                    } else {
                        send_message('public', array(
                            'status' => "\"$text\" has been added as a new safeword by @$user."
                        ));
                        send_message('dm', array(
                            'text' => "\"$text\" has been added as a new safeword. Thanks!",
                            'screen_name' => $user
                        ));
                    }
                }
            }
            break;
            
        case 'delete':
            if (!word_exists($text)) {
                tweet_error(array(
                    'error' => 'delete_nonexistant_word',
                    'word' => $text,
                    'screen_name' => $user,
                    'user_id' => $user_id
                ));
            } else if ($text == get_current_word()){
				tweet_error(array(
                    'error' => 'delete_current_word',
                    'word' => $text,
                    'screen_name' => $user,
                    'user_id' => $user_id
                ));
			} else {
                if (delete_word($text, $user)) {
                    if (DEBUG) {
                        echo "TWEET --> \"$text\" has been deleted from the safeword list by @$user.";
                    } else {
                        send_message('public', array(
                            'status' => "\"$text\" has been deleted from the safeword list by @$user."
                        ));
                        send_message('dm', array(
                            'text' => "\"$text\" has been deleted from the safeword list.",
                            'screen_name' => $user
                        ));
                    }
                }
            }
            break;  
            
        case 'help':
            $helptext = "lunchgroup_safetweeter(1) " . time() . "\n\nAdd a word: d lunchgroup add|create|new [word]\nDelete a word: d lunchgroup delete|del|remove|rm [word]";
            send_message('dm', array(
                'text' => $helptext,
                'user_id' => $user_id,
                'screen_name' => $user
            ));
            break;
		
		case 'stats':
			$count = get_word_count_for_user($user);
			$word_words = $count == 1 ? 'word' : 'words';
			$text = "You have added $count $word_words.";
			send_message('dm', array(
				'text' => $text,
				'user_id' => $user_id,
				'screen_name' => $user
			));
			break;
   }
   
}

function delete_word($word, $removedby) {
    $dbh = get_dbh();
    $query = $dbh->prepare("DELETE FROM words where word='$word'");
    return $query->execute();
}

function add_word($word, $addedby) {
    $dbh = get_dbh();
    $query = $dbh->prepare("INSERT INTO words(word,lastused, addedby) VALUES('$word', 0, '$addedby')");
    return $query->execute();
}

function get_word_count_for_user($user) {
	$dbh = get_dbh();
    $query = $dbh->prepare("SELECT COUNT(*) FROM words WHERE addedby='$user'");
	$query->execute();
	$result = $query->fetch();
	return $result[0];
}

function pick_new_word() {
	$today = time();
	$lastweek = strtotime('7 days ago');
	$dbh = get_dbh();
	
	// reset current word
	$query = $dbh->prepare("UPDATE words SET wordoftheday = NULL");
	$query->execute();
	
	// pick new word
	$query = $dbh->prepare("SELECT id FROM words WHERE lastused <= $lastweek");
	$query->execute();
	while($row = $query->fetch()) {
		$possibleWords[] = $row['id'];
	}
	$wordIndex = array_rand($possibleWords)+1;
	$word_id = $possibleWords[$wordIndex];
	
	// mark word as current
	$query = $dbh->prepare("UPDATE words SET lastused = $today, wordoftheday=1, usagecount=usagecount+1 WHERE id=$word_id");
	$query->execute();	
}
?>