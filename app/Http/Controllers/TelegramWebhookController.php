<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GayApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $update = $telegram->getWebhookUpdate();

        $text = $update->getMessage()?->getText();
        $message = $update->getMessage();
        $chatId = $message?->getChat()?->id;
        if (!$chatId) return;

        $phone = Cache::get("user:{$chatId}:phone");
        $name = Cache::get("user:{$chatId}:name");
        $passport = Cache::get("user:{$chatId}:passport");
        $step = Cache::get("user:{$chatId}:step");

        // 1. Telefon yuborgan bo'lsa
        if ($message->has('contact')) {
            $phone = $message->contact->phone_number;

            Cache::put("user:{$chatId}:phone", $phone, 600);
            Cache::forget("user:{$chatId}:name");
            Cache::forget("user:{$chatId}:passport");
            Cache::forget("user:{$chatId}:step");

            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->row([
                    Keyboard::button(['text' => '📋 Нәўбетке жазылыу']),
                ])->row([
                    Keyboard::button(['text' => '📋 Нәўбетти көриў']),
                    Keyboard::button(['text' => '👨‍💼 Админ менен байланысыў']),
                ]);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Керекли әмелди таңлаң:',
                'reply_markup' => $keyboard,
            ]);
        }

        // 2. Navbatni korish
        if ($text === '📋 Нәўбетти көриў') {
            $last_queue = GayApplication::whereHas('status', function (Builder $query) {
                $query->where('key', '=', 'completed');
            })->latest()->first();

            $queueText = $last_queue
                ? 'Акыргы болуп №' . $last_queue->queueNumber->queue_number . ' кирди'
                : 'Еле ешким кирген жок';

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $queueText,
            ]);
        }

        // 3. Navbat olish
        if ($text === '📋 Нәўбетке жазылыу') {
            Cache::put("user:{$chatId}:step", "awaiting_name", 600);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Илтимас, толық атыңызды киргизиң:',
            ]);
        }

        // 4. Step bo'yicha harakat qilish
        if ($step === 'awaiting_name') {
            Cache::put("user:{$chatId}:name", $text, 600);
            Cache::put("user:{$chatId}:step", "awaiting_passport", 600);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Паспорт серия ҳәм номериңизди киргизиң:',
            ]);
        }

        if ($step === 'awaiting_passport') {
            Cache::put("user:{$chatId}:passport", $text, 600);
            Cache::put("user:{$chatId}:step", "awaiting_photo", 600);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '📷 Айдаўшылық гүўалығын алыў ушын төленген квитанцияны жибериң.',
            ]);
        }

        if ($step === 'awaiting_photo' && $message->getPhoto()) {
            $fileName = $this->saveTelegramPhoto($message->getPhoto());

            $customer = Customer::where('phone_number', $phone)->first();
            if (!$customer) {
                $customer = Customer::create([
                    'telegram_user_id' => $chatId,
                    'phone_number' => $phone,
                    'full_name' => $name,
                    'passport' => strtoupper($passport),
                ]);
            }

            $exists = GayApplication::where('customer_id', $customer->id)
                ->whereIn('status_id', [1, 2])
                ->exists();

            if (!$exists) {
                GayApplication::create([
                    'customer_id' => $customer->id,
                    'document_path' => $fileName,
                    'status_id' => 1,
                ]);

                $messageText = "✅ Сиз табыслы дизимнен өттиңиз:\n\n📱 Телефон: $phone\n👤 ФИО: $name\n🆔 Паспорт: $passport\n🔴 Статус: Ожидает подтверждение";
            } else {
                $messageText = "❌ Сизде алдын актив жазылыў бар.";
            }

            // Tozalash
            Cache::forget("user:{$chatId}:step");
            Cache::forget("user:{$chatId}:name");
            Cache::forget("user:{$chatId}:passport");

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
            ]);
        }

        Telegram::commandsHandler(true);
        return 'ok';
    }


    // ✅ Tezkor xabar yuborish
    private function reply($telegram, $chatId, $text)
    {
        return $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    // 🖼️ Rasmni saqlash
    private function saveTelegramPhoto($photos)
    {
        $photoArray = is_array($photos) ? $photos : $photos->all();
        $lastPhoto = end($photoArray);
        $file = Telegram::getFile(['file_id' => $lastPhoto->file_id]);

        $filePath = $file->getFilePath();
        $contents = file_get_contents("https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $filePath);

        $fileName = 'uploads/images/' . uniqid() . '.jpg';
        Storage::disk('public')->put($fileName, $contents);

        return $fileName;
    }

}
