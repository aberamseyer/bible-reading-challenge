<?php

namespace Email;

interface MailSender {
  public function send_daily_verse_email($email, $name, $subject, $content, $streak);
  public function send_register_email($to, $link);
  public function send_forgot_password_email($to, $link);
}