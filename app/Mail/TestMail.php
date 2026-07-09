<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestMail extends Mailable
{
    public function build()
    {
        return $this
            ->subject('HivePanel SMTP Test')
            ->html('SMTP is working correctly for your HivePanel installation.');
    }
}