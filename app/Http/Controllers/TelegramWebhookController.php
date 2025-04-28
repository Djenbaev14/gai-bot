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

        $text=$update->getMessage()->getText();
        $message = $update->getMessage();
        $chatId = $message?->getChat()?->id;
        if (!$chatId) return;

        $phone = Cache::get("user:{$chatId}:phone");
        $name = Cache::get("user:{$chatId}:name");
        $passport = Cache::get("user:{$chatId}:passport");

        // 1. Contact bosqichi
        if ($message->has('contact')) {
            $phone = $message->contact->phone_number;
            $user = $message->getFrom();
            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->row([
                    Keyboard::button(['text' => '📋 Нәўбетти көриў']),
                    Keyboard::button(['text' => '👨‍💼 Админ менен байланысыў']),
                ]);

            Cache::put("user:{$chatId}:phone", $phone, 600);
            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Илтимас, толық атыңызды киргизиң:',
                'reply_markup' => $keyboard,
            ]);
        }
        if ($text === '📋 Нәўбетти көриў') {
            $last_queue=GayApplication::whereHas('status', function (Builder $query) {
                $query->where('key', '=', 'completed');
            })->latest()->first();
            if($last_queue){
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Акыргы болып №'.$last_queue->queueNumber->queue_number.' кирди',
                ]);
            }else{
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Хали очеред йозилган йок',
                ]);
            }
        }
        // 2. FIO bosqichi
        if ($phone && !$name) {
            Cache::put("user:{$chatId}:name", $message->text, 600);
            return $this->reply($telegram, $chatId, 'Паспорт серия ҳәм номериңизди киргизиң:');
        }

        // 3. Pasport bosqichi
        if ($phone && $name && !$passport) {
            Cache::put("user:{$chatId}:passport", $message->text, 600);
            return $this->reply($telegram, $chatId, "📷 Айдаўшылық гүўалығын алыў ушын төленген квитанцияны жибериң.");
        }

        // 4. Rasm qabul qilish bosqichi
        if ($phone && $name && $passport && $message->getPhoto()) {
            $fileName = $this->saveTelegramPhoto($message->getPhoto());

            $customer = Customer::where('phone_number', $phone)->first();
            if ($customer) {
                $exists = GayApplication::where('customer_id', $customer->id)
                ->where('status_id', 2)
                ->orWhere('status_id',1)
                ->exists();
            
                if (!$exists) {
                    GayApplication::create([
                        'customer_id' => $customer->id,
                        'document_path' => $fileName,
                        'status_id' => 1
                    ]);
                    
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "✅ Сиз табыслы дизимнен өттиңиз:\n\n📱 Телефон: $phone\n👤 ФИО: $name\n🆔 Паспорт: $passport\n🔴 Статус: Ожидает подтверждение"
                    ]);
                }else{
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "❌ Сизде алдын актив жазылыў бар."
                    ]);
                }

            }else{
                $new_customer=Customer::create([
                    'telegram_user_id' => $chatId,
                    'phone_number' => $phone,
                    'full_name' => $name,
                    'passport' => strtoupper($passport),
                ]);
                
                    GayApplication::create([
                        'customer_id' => $new_customer->id,
                        'document_path' => $fileName,
                        'status_id' => 1
                    ]); 
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "✅ Сиз табыслы дизимнен өттиңиз:\n\n📱 Телефон: $phone\n👤 ФИО: $name\n🆔 Паспорт: $passport\n🔴 Статус: Ожидает подтверждение"
                    ]);  
                     
            }

            Cache::forget("user:{$chatId}:name");
            Cache::forget("user:{$chatId}:passport");
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
