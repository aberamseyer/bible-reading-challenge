<?php

namespace Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailSenderPhpMailer implements MailSender {
  private readonly string $FROM_ADDRESS;
  private readonly string $FROM_NAME;
  private readonly string $host;
  private readonly string $user;
  private readonly string $password;

  public function __construct(string $from_address, string $from_name, string $host, string $user, string $password)
  {
    $this->FROM_ADDRESS = $from_address;
    $this->FROM_NAME = $from_name;
    $this->host = $host;
    $this->user = $user;
    $this->password = $password;
  }

  private function init_mailer()
  {
    $mail = new PHPMailer(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->SMTPDebug = PROD ? SMTP::DEBUG_OFF : SMTP::DEBUG_SERVER; //Enable verbose debug output
    $mail->isSMTP();                                                //Send using SMTP
    $mail->Host       = $this->host;                                      //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                       //Enable SMTP authentication
    $mail->Username   = $this->user;                                      //SMTP username
    $mail->Password   = $this->password;                                  //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;             //Enable implicit TLS encryption
    $mail->Port       = 587;                                        //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
    return $mail;
  }

  public function send_raw_email(string $to, string $subject, string $raw_html_email, string $uuid)
  {
    $mail = $this->init_mailer();
    $mail->setFrom($this->FROM_ADDRESS, $this->FROM_NAME);
    
    //Recipients
    $mail->addAddress($to);

    //Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->addCustomHeader('List-Unsubscribe', "https://".(explode('@', $this->FROM_ADDRESS)[1])."/unsubscribe?uuid=".$uuid);
    $mail->addCustomHeader('List-Unsubscribe-Post', "List-Unsubscribe=One-Click");
    $mail->Body    = $raw_html_email;
    $mail->AltBody = strip_tags($raw_html_email);

    return $mail->send();
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