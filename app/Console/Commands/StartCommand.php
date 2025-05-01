<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
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

            Cache::forget("user:{$chatId}:name");
            Cache::forget("user:{$chatId}:passport");
            Cache::forget("user:{$chatId}:step");
            Cache::forget("user:{$chatId}:id");
            Cache::forget("user:{$chatId}:number");
            Cache::forget("user:{$chatId}:fileName");
            Cache::forget("user:{$chatId}:region");
            Cache::forget("user:{$chatId}:branch");

            $phone = Cache::get("user:{$chatId}:phone");
            if ($phone) {
                // ✅ Telefon raqam oldin yuborilgan — Asosiy menyuni ko‘rsatamiz
                $keyboard = Keyboard::make()
                    ->setResizeKeyboard(true)
                    ->setOneTimeKeyboard(false)
                    ->row([
                        Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                        Keyboard::button(['text' => '📋 Наўбетти тексериў']),
                    ]);

                return $this->replyWithMessage([
                    'text' => "Керекли әмелди сайлаң:",
                    'reply_markup' => $keyboard,
                ]);
            }
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
                'text' => "Айдаўшылық имтиҳаны ушын наўбет алыў ботына хош келибсиз!\nНаўбет алыў ушын төмендеги түймени басың\n\nДаптердеги наубетлер 3 филиал иске тускени ушын бийкар етилди ❌даптерде барлар боттан наубет алын ⭕️",
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
