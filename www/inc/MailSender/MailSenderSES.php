<?php

namespace Email;

class MailSenderSES implements MailSender {
  public function send_dynamic_email(string $to, string $template, array $dynamic_data) {
		throw new Exception("Unimplemented MailSenderSES->send_dynamic_email");
  }
}