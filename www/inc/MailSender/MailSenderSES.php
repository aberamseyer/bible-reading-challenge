<?php

namespace Email;

class MailSenderSES implements MailSender {
  public function send_daily_verse_email($email, $name, $subject, $content, $streak) {
		throw new Exception("Unimplemented MailSenderSES->send_daily_verse_email");
  }

  function send_register_email($to, $link) {		
		throw new Exception("Unimplemented MailSenderSES->send_register_email");
  }
  
	function send_forgot_password_email($to, $link) {
		throw new Exception("Unimplemented MailSenderSES->send_forgot_password_email");
	}
}