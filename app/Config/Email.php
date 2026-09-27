<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail = '';
    public string $fromName  = '';

    public string $protocol = 'smtp';
    public string $SMTPHost = '';
    public string $SMTPUser = '';
    public string $SMTPPass = '';
    public int $SMTPPort    = 587;
    public string $SMTPCrypto = 'tls';
    public bool $SMTPKeepAlive = false;
    public int $SMTPTimeout = 5;

    public string $mailType = 'html';
    public string $charset  = 'UTF-8';
    public bool $validate   = true;
    public int $priority    = 3;
    public string $CRLF     = "\r\n";
    public string $newline  = "\r\n";

    public function __construct()
    {
        parent::__construct();

        $this->fromEmail = env('email.fromEmail', 'ops@sanvima.com');
        $this->fromName  = env('email.fromName', 'Sanvima Operations');
        $this->SMTPHost  = env('email.SMTPHost', '');
        $this->SMTPUser  = env('email.SMTPUser', '');
        $this->SMTPPass  = env('email.SMTPPass', '');
        $this->SMTPPort  = (int) env('email.SMTPPort', 587);
        $this->SMTPCrypto = env('email.SMTPCrypto', 'tls');
        $this->protocol  = env('email.protocol', 'smtp');
    }
}
