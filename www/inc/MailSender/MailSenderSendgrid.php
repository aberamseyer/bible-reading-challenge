<?php

namespace Email;

class MailSenderSendgrid implements MailSender {
  public function send_daily_verse_email($email, $name, $subject, $content, $streak) {
    $body = [
			"from" => [
				"email" => "daily@uoficoc.com",
				"name" => "U of I Christians on Campus"
			],
			"template_id" => SENDGRID_DAILY_EMAIL_TEMPLATE,
			"personalizations" => [
				[
					"to" => [[ "email" => $email ]],
					"dynamic_template_data" => [
						"subject" => $subject,
						"name" => $name,
						"html" => $content,
						"streak" => $streak
					]
				]
			]
		];
		curl_post_json("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.SENDGRID_API_KEY], $body);
  }

  function send_register_email($to, $link) {		
		$body = [
			"from" => [
				"email" => "accounts@uoficoc.com",
				"name" => "U of I Christians on Campus Accounts"
			],
			"template_id" => SENDGRID_REGISTER_EMAIL_TEMPLATE,
			"personalizations" => [
				[
					"to" => [[ "email" => $to ]],
					"dynamic_template_data" => [ "confirm_link" => $link ]
				]
			]
		];
		curl_post_json("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.SENDGRID_API_KEY], $body);
  }
  
	function send_forgot_password_email($to, $link) {
		$body = [
			"from" => [
				"email" => "accounts@uoficoc.com",
				"name" => "U of I Christians on Campus Accounts"
			],
			"template_id" => SENDGRID_FORGOT_PASSWORD_TEMPLATE,
			"personalizations" => [
				[
					"to" => [[ "email" => $to ]],
					"dynamic_template_data" => [ "reset_link" => $link ]
				]
			]
		];
		curl_post_json("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.SENDGRID_API_KEY], $body); 
	}

}