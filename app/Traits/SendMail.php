<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;

trait SendMail
{
    public function otp_send($to, $otp, $for)
    {
        switch ($for) {
            case 1:
                $subject = "WaxCraft Email Address Validation";
                $messageBody = "Dear, " . $to . ", OTP for Forgot Password of your ChillOptix account is: " . $otp;
                break;
            case 2:
                $subject = "OTP For Terminate Active Session";
                $messageBody = "Dear, " . $to . ", OTP for termination of your previous active session is: " . $otp;
                break;
            default:
                $subject = "No Subject";
                $messageBody = "No message content.";
                break;
        }

        // Send the email using raw content
        try {
            Mail::raw($messageBody, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
            return false;
        }
    }
}
