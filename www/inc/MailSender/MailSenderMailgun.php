<?php

namespace Email;

class MailSenderMailgun implements MailSender {
  private readonly string $API_DOMAIN;
  private readonly string $APP_DOMAIN;
  private readonly string $API_KEY;
  private readonly string $FROM_ADDRESS;
  private readonly string $FROM_NAME;

  public function __construct($app_domain, $api_key, $from_address, $from_name)
  {
    $this->API_DOMAIN = "api.mailgun.net";
    $this->APP_DOMAIN = $app_domain;
    $this->API_KEY = $api_key;
    $this->FROM_ADDRESS = $from_address;
    $this->FROM_NAME = $from_name;
  }

  public function send_raw_email(string $to, string $subject, string $raw_html_email, string $uuid)
  {
    curl_post_form(
      $this->API_DOMAIN."/v3/".$this->APP_DOMAIN."/messages",
      [ 'Authorization: Basic '.base64_encode('api:'.$this->API_KEY) ],
      [
        'from' => $this->FROM_NAME." <".$this->FROM_ADDRESS.">",
        'to' => $to,
        'subject' => $subject,
        'html' => $raw_html_email,
      ]
    );
  }

  /**
   * $to should be of form:
   * [
   *    'email_address' => [ 'key1' => 'val1', .... ]
   * ]
   * were all entries have the exact same keys with corresponding values. the keys and values
   * will be subsituted into the raw_html_email
   */
  public function send_bulk_email(array $to, string $subject, string $raw_html_email, array $uuids)
  {
    // https://documentation.mailgun.com/docs/mailgun/user-manual/sending-messages/#batch-sending
    curl_post_form(
      $this->API_DOMAIN."/v3/".$this->APP_DOMAIN."/messages",
      [ 'Authorization: Basic '.base64_encode('api:'.$this->API_KEY) ],
      [
        'from' => $this->FROM_NAME." <".$this->FROM_ADDRESS.">",
        'to' => array_keys($to),
        'subject' => $subject,
        'html' => $raw_html_email,
        'recipient-variables' => json_encode($to, JSON_UNESCAPED_SLASHES)
      ]
    );
  }
}
