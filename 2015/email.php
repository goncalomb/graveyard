<?php

// requires jQuery

function obfuscate_email_address($email) {
	// ROT13 cipher.
	$email = preg_replace_callback('/[a-zA-Z]+/', function($matches) {
		return str_rot13($matches[0]);
	}, $email);
	// Reverse order.
	preg_match_all('/./us', $email, $matches);
	$email = join('', array_reverse($matches[0]));
	// Encode as HTML entities.
	$email = mb_encode_numericentity($email, array(0x0, 0xffff, 0, 0xffff), 'UTF-8');
	// Output link and javascript code.
	echo '<a href="#" data-e="', $email, '">[Enable JavaScript to view the email address]</a>';
	echo '<script>(function(e){e.html(e.attr("data-e").replace(/[a-zA-Z]/g,function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);}).split("").reverse().join("")).attr("href","mailto:"+e.text()).next().remove()})($("a").last())</script>';
}

?>
