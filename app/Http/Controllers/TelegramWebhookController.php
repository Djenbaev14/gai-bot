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
            Cache::forget("user:{$chatId}:id");
            Cache::forget("user:{$chatId}:fileName");

            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->row([
                    Keyboard::button(['text' => '📋 Наўбетке жазылыў']),
                ]);
                // ->row([
                //     Keyboard::button(['text' => '📋 Нәўбетти көриў']),
                //     Keyboard::button(['text' => '👨‍💼 Админ менен байланысыў']),
                // ]);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Керекли әмелди сайлаң:',
                'reply_markup' => $keyboard,
            ]);
        }

        // 2. Navbatni korish
        // if ($text === '📋 Нәўбетти көриў') {
        //     $last_queue = GayApplication::whereHas('status', function (Builder $query) {
        //         $query->where('key', '=', 'completed');
        //     })->latest()->first();

        //     $queueText = $last_queue
        //         ? 'Акыргы болуп №' . $last_queue->queueNumber->queue_number . ' кирди'
        //         : 'Еле ешким кирген жок';

        //     return $telegram->sendMessage([
        //         'chat_id' => $chatId,
        //         'text' => $queueText,
        //     ]);
        // }

        if ($text === '📋 Наўбетке жазылыў') {
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
                'text' => 'Паспорт серия ҳәм номериңизди киргизиң AB5557766:',
            ]);
        }

        if ($step === 'awaiting_passport') {
            Cache::put("user:{$chatId}:passport", $text, 600);
            Cache::put("user:{$chatId}:step", "awaiting_photo", 600);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '📷 Айдаўшылық гүўалығын алыў ушын төленген квитанция, экзамен билети ямаса басқа тастыйықлаўшы хужжетти  жибериң.',
            ]);
        }

        if ($step === 'awaiting_photo' && $message->getPhoto()) {

            $customer = Customer::where('telegram_user_id', $chatId)->first();
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
                $fileName = $this->saveTelegramPhoto($message->getPhoto());
                GayApplication::create([
                    'customer_id' => $customer->id,
                    'document_path' => $fileName,
                    'status_id' => 1,
                ]);

                $messageText = "✅Администрацияға наўбет ушын сораў жиберилди !\n\n Наўбетиңизди күтиң тез арада сизге ңаўбет номери келеди, ботты өширип тасламаң ❌";
                
                // Tozalash
                Cache::forget("user:{$chatId}:step");
                Cache::forget("user:{$chatId}:name");
                Cache::forget("user:{$chatId}:passport");

                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $messageText,
                ]);
            } else {
                $gay_application=GayApplication::where('customer_id', $customer->id)
                ->whereIn('status_id', [1, 2])->first();
                if($gay_application->status->key=='active'){
                    $fileName = $this->saveTelegramPhoto($message->getPhoto());
                    $number=$gay_application->queueNumber->queue_number;

                    Cache::put("user:{$chatId}:step", "new_queue", 600);
                    Cache::put("user:{$chatId}:fileName", $fileName, 600);
                    Cache::put("user:{$chatId}:id", $gay_application->id, 600);
                    Cache::put("user:{$chatId}:number", $number, 600);
                    

                    $messageText = "❌ Сизде №$number наўбети бар соны бийкарлап таза наўбет алмақшысызба ?";
                    
                    $keyboard = Keyboard::make()
                        ->setResizeKeyboard(true)
                        ->setOneTimeKeyboard(false)
                        ->row([
                            Keyboard::button(['text' => 'Аўа таза нәубет аламан']),
                            Keyboard::button(['text' => 'Яқ наўбетимде қаламан']),
                        ]);
                    return $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'reply_markup'=>$keyboard
                    ]);
                }else{
                    $messageText = "❌ Еле сиздин наубет актив болмады киттай кутин";
                    // Tozalash
                    Cache::forget("user:{$chatId}:step");
                    Cache::forget("user:{$chatId}:name");
                    Cache::forget("user:{$chatId}:passport");
        
                    return $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $messageText,
                    ]);
                }
            }

        }
        if($step === 'new_queue' && $text==='Аўа таза нәубет аламан'){

            $customer = Customer::where('telegram_user_id', $chatId)->first();
            GayApplication::where('id','=',Cache::get("user:{$chatId}:id"))->update([
                'status_id'=>4
            ]);
            GayApplication::create([
                'customer_id' => $customer->id,
                'document_path' => Cache::get("user:{$chatId}:fileName"),
                'status_id' => 1,
            ]);
            
            $messageText = "✅Администрацияға наўбет ушын сораў жиберилди !\n\n Наўбетиңизди күтиң тез арада сизге ңаўбет номери келеди, ботты өширип тасламаң ❌";
                
            // Tozalash
            Cache::forget("user:{$chatId}:step");
            Cache::forget("user:{$chatId}:name");
            Cache::forget("user:{$chatId}:passport");
            Cache::forget("user:{$chatId}:fileName");
            Cache::forget("user:{$chatId}:number");
            Cache::forget("user:{$chatId}:id");
            $removeKeyboard = Keyboard::remove();

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'reply_markup'=>$removeKeyboard
            ]);
        }
        if($step === 'new_queue' && $text==='Яқ наўбетимде қаламан'){

            $number=Cache::get("user:{$chatId}:number");

            Cache::forget("user:{$chatId}:step");
            Cache::forget("user:{$chatId}:name");
            Cache::forget("user:{$chatId}:passport");
            Cache::forget("user:{$chatId}:fileName");
            Cache::forget("user:{$chatId}:number");
            Cache::forget("user:{$chatId}:id");
            $removeKeyboard = Keyboard::remove();

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Сиздин №$number наубетиниз оз орнында калды",
                'reply_markup'=>$removeKeyboard
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
