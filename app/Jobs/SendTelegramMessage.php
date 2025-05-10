<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chatId;
    public $message;

    public function __construct($chatId, $message)
    {
        $this->chatId = $chatId;
        $this->message = $message;
    }

    public function handle()
    {
        $telegram = Telegram::bot('mybot');

        try {
            $telegram->sendMessage([
                'chat_id' => $this->chatId,
                'text' => $this->message,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Telegram send failed: ' . $th->getMessage());
        }
    }
}
