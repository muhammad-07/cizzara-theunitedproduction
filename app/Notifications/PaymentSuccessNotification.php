<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification
{
    use Queueable;

    protected $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Payment Confirmation')
            ->line('Your payment was successful!')
            ->line('Thank you for your purchase.')
            ->line('Payment Details:')
            ->line('Amount: $' . ($this->payment->price))
            ->line('Transaction ID: ' . $this->payment->stripe_payment_id);
    }
}