<?php

namespace Email;

class MailSenderSendgrid implements MailSender {
	private readonly string $daily_email_template;
	private readonly string $register_email_template;
	private readonly string $forgot_password_template;
	private readonly string $api_key;
	private readonly string $from_email;
	private readonly string $from_name;

	public function __construct(
			$sendgrid_api_key, 
			$sendgrid_daily_email_template, 
			$sendgrid_register_email_template, 
			$sendgrid_forgot_password_template,
			$from_email,
			$from_name)
	{
		$this->api_key = $sendgrid_api_key;
		$this->daily_email_template = $sendgrid_daily_email_template;
		$this->register_email_template = $sendgrid_register_email_template;
		$this->forgot_password_template = $sendgrid_forgot_password_template;
		$this->from_email = $from_email;
		$this->from_name = $from_name;
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
				"email" => $this->from_email,
				"name" => $this->from_name
			],
			"template_id" => $template,
			"personalizations" => [
				[
					"to" => [ [ "email" => $to ] ],
					"dynamic_template_data" => $dynamic_data
				]
			]
		];
		curl_post_form("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.$this->api_key], $body, true); 
	}

  public function send_raw_email(string $to, string $subject, string $raw_html_email)
	{
		throw new \Exception("Unimplemented MailSenderSendgrid->send_raw_email");
	}
	public function send_bulk_email(array $to, string $subject, string $raw_html_email)
	{
		throw new \Exception("Unimplemented MailSenderSendgrid->send_bulk_email");
	}
}