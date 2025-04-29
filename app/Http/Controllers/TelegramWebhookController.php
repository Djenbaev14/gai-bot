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
            $newCustomer=Customer::updateOrCreate([
                'telegram_user_id'=>$chatId,
                'phone_number'=>$phone
            ]);
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
                    Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                    Keyboard::button(['text' => '📋 Наўбетти тексериў']),
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
        if ($text === '📋 Наўбетти тексериў') {

            $customer = Customer::where('telegram_user_id','=',$chatId)->first();
            $myQueue=GayApplication::where('customer_id',$customer->id)->where('status_id',2)->latest()->first();
            Log::info($myQueue);
            $lastQueue = GayApplication::whereHas('status', function (Builder $query) {
                $query->where('key', '=', 'completed');
            })->latest()->first();
            $lastQueueNumber = $lastQueue?->queueNumber?->queue_number ?? 0;

            $lastQueueText=$lastQueueNumber>0 ? "✅ Ақырғы кирген наўбет:  № $lastQueueNumber": "Еле ешким тестке кирген жок";
            if($myQueue){
                $myQueueNumber=$myQueue->queueNumber->queue_number;
                $waitingCount = GayApplication::whereHas('status', function (Builder $query) {
                    $query->where('key', '=','active');
                })->whereHas('queueNumber', function (Builder $query) use ($lastQueueNumber, $myQueueNumber) {
                    $query->where('queue_number', '>', $lastQueueNumber)
                          ->where('queue_number', '<', $myQueueNumber);
                })->count();
                $waiting=$waitingCount>0 ? "❇️ Сиздиң алдыңызда $waitingCount пуҳара бар": "Сиздиң алдыңызда ешким жок";
                
                $telegram->sendMessage([
                    'chat_id' => $chatId, // Foydalanuvchining chat_id sini olish
                    'text' => "📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,2,3 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy",
                ]);
            }else{
                $text='⭕️ Сизде актив наубет жок';
                $telegram->sendMessage([
                    'chat_id' => $chatId, // Foydalanuvchining chat_id sini olish
                    'text' => "📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n$text\n\n$lastQueueText\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,2,3 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy",
                ]);
            }
        }

        if ($text === '✍️ Наўбетке жазылыў') {
            Cache::put("user:{$chatId}:step", "awaiting_name", 600);

            $keyboard = Keyboard::remove();
            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Фамилия атыңызды толық киритин ( Нокисбаев Оралбай):',
                'reply_markup' => $keyboard,
            ]);
        }

        // 4. Step bo'yicha harakat qilish
        if ($step === 'awaiting_name') {
            Cache::put("user:{$chatId}:name", $text, 600);
            Cache::put("user:{$chatId}:step", "awaiting_passport", 600);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Паспорт серия ҳәм номериңизди киргизиң AA1234567:',
            ]);
        }

        if ($step === 'awaiting_passport') {
            if (!preg_match('/^[A-Z]{2}\d{7}$/', $text)) {
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Паспорт форматы қате. Дурус форматта киргизиң: AA1234567.',
                ]);
            }
            Cache::put("user:{$chatId}:passport", $text, 600);
            Cache::put("user:{$chatId}:step", "awaiting_photo", 600);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '📷 Айдаўшылық гүўалығын алыў ушын төленген квитанция, экзамен билети ямаса басқа тастыйықлаўшы хужжетти  жибериң.',
            ]);
        }

        if ($step === 'awaiting_photo') {
            if(!$message->getPhoto()){
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Суурет жиберин',
                ]);
            }
            $customer = Customer::where('telegram_user_id', $chatId)->first();
            if ($customer->full_name === null) {
                $customer->update([
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
                $keyboard = Keyboard::make()
                    ->setResizeKeyboard(true)
                    ->setOneTimeKeyboard(false)
                    ->row([
                        Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                        Keyboard::button(['text' => '📋 Наўбетти тексериў']),
                    ]);

                $messageText = "✅Администрацияға наўбет ушын сораў жиберилди !\n\n Наўбетиңизди күтиң тез арада сизге ңаўбет номери келеди, ботты өширип тасламаң ❌";
                
                // Tozalash
                Cache::forget("user:{$chatId}:step");
                Cache::forget("user:{$chatId}:name");
                Cache::forget("user:{$chatId}:passport");

                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'reply_markup'=>$keyboard
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
                    $keyboard = Keyboard::make()
                    ->setResizeKeyboard(true)
                    ->setOneTimeKeyboard(false)
                    ->row([
                        Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                        Keyboard::button(['text' => '📋 Наўбетти тексериў']),
                    ]);
        
                    return $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'reply_markup'=>$keyboard
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
            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->row([
                    Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                    Keyboard::button(['text' => '📋 Наўбетти тексериў']),
                ]);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'reply_markup'=>$keyboard
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
            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->row([
                    Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                    Keyboard::button(['text' => '📋 Наўбетти тексериў']),
                ]);

            return $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Сиздин №$number наубетиниз оз орнында калды",
                'reply_markup'=>$keyboard
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
