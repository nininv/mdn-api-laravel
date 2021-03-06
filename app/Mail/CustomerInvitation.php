<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerInvitation extends Mailable
{
    use Queueable, SerializesModels;
    public $password;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($password)
    {
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.customer-invitation')
                    ->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject('Welcome to Machine CDN')
                    ->with([ 'password' => $this->password ]);
    }
}
