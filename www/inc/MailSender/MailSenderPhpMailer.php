<?php

namespace Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailSenderPhpMailer implements MailSender {
  private readonly string $FROM_ADDRESS;
  private readonly string $FROM_NAME;
  private readonly PHPMailer $mail;

  public function __construct(string $from_address, string $from_name, string $host, string $user, string $password)
  {
    $this->FROM_ADDRESS = $from_address;
    $this->FROM_NAME = $from_name;
    $this->mail = new PHPMailer(true);
    $this->mail->CharSet = PHPMailer::CHARSET_UTF8;
    $this->mail->SMTPDebug = PROD ? SMTP::DEBUG_OFF : SMTP::DEBUG_SERVER; //Enable verbose debug output
    $this->mail->isSMTP();                                                //Send using SMTP
    $this->mail->Host       = $host;                                      //Set the SMTP server to send through
    $this->mail->SMTPAuth   = true;                                       //Enable SMTP authentication
    $this->mail->Username   = $user;                                      //SMTP username
    $this->mail->Password   = $password;                                  //SMTP password
    $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;             //Enable implicit TLS encryption
    $this->mail->Port       = 587;                                        //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
  }

  public function send_raw_email(string $to, string $subject, string $raw_html_email, string $uuid)
  {
    $this->mail->setFrom($this->FROM_ADDRESS, $this->FROM_NAME);
    
    //Recipients
    $this->mail->addAddress($to);

    //Content
    $this->mail->isHTML(true);
    $this->mail->Subject = $subject;
    $this->mail->addCustomHeader('List-Unsubscribe', "https://".(explode('@', $this->FROM_ADDRESS)[1])."/unsubscribe?uuid=".$uuid);
    $this->mail->addCustomHeader('List-Unsubscribe-Post', "List-Unsubscribe=One-Click");
    $this->mail->Body    = $raw_html_email;
    $this->mail->AltBody = strip_tags($raw_html_email);

    return $this->mail->send();
  }
	public function send_bulk_email(array $to, string $subject, string $raw_html_email, array $uuids)
	{
    $i = 0;
		foreach($to as $email => $substitutions) {
      foreach($substitutions as $sub_key => $sub_value) {
        $raw_html_email = str_replace('%recipient.'.$sub_key.'%', $sub_value, $raw_html_email);
      }
      $this->send_raw_email($email, $subject, $raw_html_email, $uuids[$i++]);
    }
	}
}