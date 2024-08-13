<?php

namespace Email;

interface MailSender {
  public function send_dynamic_email(string $to, string $template, array $dynamic_data);
  public function daily_email_template();
  public function register_email_template();
  public function forgot_password_template();
  public function send_raw_email(string $to, string $subject, string $raw_html_email);
  public function send_bulk_email(array $to, string $subject, string $raw_html_email);
}