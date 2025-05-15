<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\GayApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\Api;

class SendCancellationMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $applicationId;

    public function __construct($applicationId)
    {
        $this->applicationId = $applicationId;
    }

    public function handle()
    {
        $record = GayApplication::find($this->applicationId);
        if (!$record) return;

        $customer = Customer::find($record->customer_id);
        if (!$customer || !$customer->telegram_user_id) return;

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $telegram->sendMessage([
            'chat_id' => $customer->telegram_user_id, // Foydalanuvchining chat_id sini olish
            'text' => "<blockquote> ❌ Сизиң дизимнен өтиў сораўыңыз бийкар етилди!</blockquote>",
            'parse_mode' => 'HTML'
        ]);


    }
}
