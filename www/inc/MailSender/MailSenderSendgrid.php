<?php

namespace Email;

class MailSenderSendgrid implements MailSender {
	private readonly string $daily_email_template;
	private readonly string $register_email_template;
	private readonly string $forgot_password_template;
	private readonly string $api_key;

	public function __construct(
			$sendgrid_api_key, 
			$sendgrid_daily_email_template, 
			$sendgrid_register_email_template, 
			$sendgrid_forgot_password_template)
	{
		$this->api_key = $sendgrid_api_key;
		$this->daily_email_template = $sendgrid_daily_email_template;
		$this->register_email_template = $sendgrid_register_email_template;
		$this->forgot_password_template = $sendgrid_forgot_password_template;
	}

	public function daily_email_template()
	{
		return $this->daily_email_template;
	}
	
	public function register_email_template()
	{
		return $this->register_email_template;
	}

	public function forgot_password_template()
	{
		return $this->forgot_password_template;
	}

	public function send_dynamic_email($to, $template, $dynamic_data)
	{
		$body = [
			"from" => [
				"email" => "accounts@uoficoc.com",
				"name" => "U of I Christians on Campus Accounts"
			],
			"template_id" => $template,
			"personalizations" => [
				[
					"to" => [[ "email" => $to ]],
					"dynamic_template_data" => $dynamic_data
				]
			]
		];
		curl_post_json("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.$this->api_key], $body); 
	}

}