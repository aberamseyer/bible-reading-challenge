<?php

namespace Email;

interface MailSender {
  public function send_dynamic_email(string $to, string $template, array $dynamic_data);
}