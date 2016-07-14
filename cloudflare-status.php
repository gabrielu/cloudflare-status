<?php
require 'lib/simple_html_dom.php';
require 'lib/phpmailer/class.phpmailer.php';
require 'lib/phpmailer/class.smtp.php';

define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', '465');
define('SMTP_USER', '__USERNAME__');
define('SMTP_PASSWORD', '__PASSWORD__');
define('EMAIL_FROM', '__USERNAME__@sendgrid.com');
define('EMAIL_RECIPIENT', '__RECIPIENT@EXAMPLE.COM__');

define('LOG_FILE', getcwd() . '/cloudflarestatus.log');
define('PARSE_FILE', getcwd() . '/cloudflarestatus.html');

// load past statuses
$stored_locations = array();
if (file_exists(LOG_FILE)) {
	$stored_locations = unserialize(file_get_contents(LOG_FILE));
}

$status_changed = array();
$failed_parse = false;
$previous_failed_parse = file_exists(PARSE_FILE);

$html = file_get_html('https://www.cloudflarestatus.com/');

// parse all locations
$locations = array();
foreach($html->find('.component-inner-container') as $location) {
	$locations[trim($location->find('.name', 0)->plaintext)] = trim($location->find('.component-status', 0)->plaintext);
}

if (empty($locations)) {
	$failed_parse = true;
	file_put_contents(PARSE_FILE, $html);
}

// compare current location status against db/stored status
if (!empty($locations)) {
	foreach ($locations as $name=>$status) {
		if (!array_key_exists($name, $stored_locations) || (array_key_exists($name, $stored_locations) && $stored_locations[$name] != $status)) {
			$status_changed[$name] = $locations[$name];
		}
	}
}

// save current results
file_put_contents(LOG_FILE, serialize($locations));

// email status changes or when we can't parse any locations
if (!empty($status_changed) || ($failed_parse && !$previous_failed_parse)) {
	$mail = new PHPMailer();

	$mail->IsSMTP();
	// enables SMTP debug information (for testing)
	// 1 = errors and messages
	// 2 = messages only
	$mail->SMTPDebug  = 1;
	$mail->SMTPAuth = true;
	$mail->SMTPSecure = 'ssl';
	$mail->Host = SMTP_HOST;
	$mail->Port = SMTP_PORT;
	$mail->Username = SMTP_USER;
	$mail->Password = SMTP_PASSWORD;

	$mail->SetFrom(EMAIL_FROM, gethostname());
	// $mail->AddReplyTo('name@yourdomain.com','First Last');

	if ($failed_parse) {
		$mail->Subject = 'CloudFlare Parse Failed';
		$mail->Body = 'Parsing of cloudflarestatus.com failed. Review the attached file and remove ' . PARSE_FILE . ' from the server when you want to be notified about failed parses again. You will not continue to receive failed parse messages until you delete this file on the server.';
		$mail->AddAddress(EMAIL_RECIPIENT);
		$mail->AddAttachment(PARSE_FILE);
	} else {
		$mail->Subject = 'CloudFlare Status Updates';
		$mail->Body = print_r($status_changed, true);
		// $mail->MsgHTML($body);
		$mail->AddAddress(EMAIL_RECIPIENT);
	}

	if(!$mail->Send()) {
		echo 'Mailer Error: ' . $mail->ErrorInfo;
	} else {
		echo 'Message sent!';
	}
}
