<?php

namespace Email;

interface MailSender {
  public function send_raw_email(string $to, string $subject, string $raw_html_email, string $uuids);
  public function send_bulk_email(array $to, string $subject, string $raw_html_email, array $uuid);
}