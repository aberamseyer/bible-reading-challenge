<?php

namespace Email;

class MailSenderSES implements MailSender {
  public function send_dynamic_email(string $to, string $template, array $dynamic_data)
  {
		throw new \Exception("Unimplemented MailSenderSES->send_dynamic_email");
  }
  public function daily_email_template() 
  {
    throw new \Exception("Unimplemented MailSenderSES->daily_email_template");
  }
  public function register_email_template() 
  {
    throw new \Exception("Unimplemented MailSenderSES->register_email_template");
  }
  public function forgot_password_template() 
  {
    throw new \Exception("Unimplemented MailSenderSES->forgot_password_template");
  }
  public function send_raw_email(string $to, string $subject, string $raw_html_email)
  {
    throw new \Exception("Unimplemented MailSenderSES->send_raw_email");
  }
	public function send_bulk_email(array $to, string $subject, string $raw_html_email)
	{
		throw new \Exception("Unimplemented MailSenderSES->send_bulk_email");
	}
}