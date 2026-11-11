<?php

namespace App\Notifications;

use App\Services\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOTPNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp,
        public string $purpose = OtpService::REGISTER,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $isReset = $this->purpose === OtpService::RESET;
        $isEmailChange = $this->purpose === OtpService::EMAIL_CHANGE;

        return (new MailMessage)
            ->subject(match (true) {
                $isReset => 'رمز استعادة كلمة السر — تطبيق نفس',
                $isEmailChange => 'رمز تأكيد البريد الجديد — تطبيق نفس',
                default => 'رمز التحقق الخاص بك في تطبيق نفس',
            })
            ->greeting('أهلاً بك في تطبيق نفس!')
            ->line(match (true) {
                $isReset => 'رمز استعادة كلمة السر هو:',
                $isEmailChange => 'رمز تأكيد بريدك الإلكتروني الجديد هو:',
                default => 'رمز التحقق الخاص بك لتفعيل الحساب هو:',
            })
            ->line('**'.$this->otp.'**')
            ->line('هذا الرمز صالح لمدة 5 دقائق فقط.')
            ->line('إذا لم تقم بطلب هذا الرمز، يمكنك تجاهل هذه الرسالة بأمان.');
    }
}
