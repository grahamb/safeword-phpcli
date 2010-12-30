#!/usr/bin/php
<?

require 'safetweeter.php';

$direct_messages = get_direct_messages();
foreach ($direct_messages as $message) {
    process_direct_message($message);
}

?>