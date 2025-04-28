<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class StartCommand extends Command
{
    protected string $name = 'start';

    protected string $description = 'Ботты иске түсириў буйрығы';

    public function handle()
    {
        try {
            $chatId = $this->getUpdate()->getMessage()->getChat()->getId();

            // Klaviatura yaratamiz
            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(true)
                ->row([
                    Keyboard::button([
                        'text' => '📞 Контакт жибериў',
                        'request_contact' => true,
                    ]),
                ]);

            // Foydalanuvchiga xabar va tugma yuboramiz
            $this->replyWithMessage([
                'text' => "Хош келдиңиз! Телефон номериңизди жибериў ушын төмендеги түймени басың.",
                'reply_markup' => $keyboard,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in StartCommand:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    public function makeTelegram($telegram)
    {
        $this->telegram = $telegram;
    }

    public function makeUpdate($update)
    {
        $this->update = $update;
    }
}
