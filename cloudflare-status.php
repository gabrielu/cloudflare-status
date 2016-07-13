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

// load past statuses
$log_file = 'cloudflarestatus.log';
$stored_locations = array();
if (file_exists($log_file)) {
	$stored_locations = unserialize(file_get_contents($log_file));
}

$status_changed = array();

$html = file_get_html('https://www.cloudflarestatus.com/');

// parse all locations
$locations = array();
foreach($html->find('.component-inner-container') as $location) {
	$locations[trim($location->find('.name', 0)->plaintext)] = trim($location->find('.component-status', 0)->plaintext);
}

// compare current location status against db/stored status
foreach ($locations as $name=>$status) {
	if (!array_key_exists($name, $stored_locations) || (array_key_exists($name, $stored_locations) && $stored_locations[$name] != $status)) {
		$status_changed[$name] = $locations[$name];
	}
}

// save current results
file_put_contents($log_file, serialize($locations));

// email status changes
if (!empty($status_changed)) {
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
	$mail->Password = SMTP_PASSWORD;            // GMAIL password

	$mail->SetFrom(EMAIL_FROM);
	// $mail->AddReplyTo('name@yourdomain.com','First Last');
	$mail->Subject = 'CloudFlare Status Updates';
	$mail->Body = print_r($status_changed, true);
	// $mail->MsgHTML($body);
	$mail->AddAddress(EMAIL_RECIPIENT);
	// $mail->AddAttachment('images/phpmailer.gif');      // attachment

	if(!$mail->Send()) {
		echo 'Mailer Error: ' . $mail->ErrorInfo;
	} else {
		echo 'Message sent!';
	}
}
