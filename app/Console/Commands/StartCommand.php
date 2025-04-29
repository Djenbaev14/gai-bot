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
                'text' => "Айдаўшылық имтиҳаны ушын наўбет алыў ботына хош келибсиз!\nНаўбет алыў ушын төмендеги түймени басың\n\nКағәздағы наўбетлер 2200 ден басланады ҳәм сол кағаздағы наўбетлер жуўмакланғанынан кейин Телеграм арқалы келген наўбетлер оқылады ✅\n\nЕгер сиз қағазда 2200-4000 аралығындағы наўбетте болсаныз боттан наўбет алмаң ❌",
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
