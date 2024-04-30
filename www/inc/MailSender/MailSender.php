<?php

namespace Email;

interface MailSender {
  public function send_dynamic_email(string $to, string $template, array $dynamic_data);
  public function daily_email_template();
  public function register_email_template();
  public function forgot_password_template();
}