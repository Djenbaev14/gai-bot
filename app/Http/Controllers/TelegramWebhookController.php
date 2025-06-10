<?php

namespace App\Http\Controllers;

use App\Console\Commands\StartCommand;
use App\Models\Branch;
use App\Models\BranchRegion;
use App\Models\Customer;
use App\Models\GayApplication;
use App\Models\Region;
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
        try {
            $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
            $update = $telegram->getWebhookUpdate();
            
            if ($update->getMessage() instanceof \Telegram\Bot\Objects\Message) {
                $text = $update->getMessage()->getText();
            } else {
                // Xato yoki kerakli obyekt topilmadi
                $text = null;
            }
            $message = $update->getMessage();
            $chatId = $message?->getChat()?->id;
            if (!$chatId) return;
    
            $phone = Cache::get("user:{$chatId}:phone");
            $name = Cache::get("user:{$chatId}:name");
            $region = Cache::get("user:{$chatId}:region");
            $branch = Cache::get("user:{$chatId}:branch");
            $passport = Cache::get("user:{$chatId}:passport");
            $step = Cache::get("user:{$chatId}:step");
    
            // 1. Telefon yuborgan bo'lsa
            if ($message->has('contact')) {
                $contactUserId = $message->contact->user_id ?? null; 

                if ($contactUserId !== $message->from->id) {
                    return $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => '❗️ Илтимас, "Контакт жибериў" түймесинен пайдаланың. Басқа адамның номерин жибермең.',
                    ]);
                }
                $phone = $message->contact->phone_number;
                Customer::updateOrCreate(
                    ['telegram_user_id' => $chatId],
                    ['phone_number' => $phone]
                );
                Cache::put("user:{$chatId}:phone", $phone, 600);
                Cache::forget("user:{$chatId}:name");
                Cache::forget("user:{$chatId}:passport");
                Cache::forget("user:{$chatId}:step");
                Cache::forget("user:{$chatId}:id");
                Cache::forget("user:{$chatId}:number");
                Cache::forget("user:{$chatId}:fileName");
                Cache::forget("user:{$chatId}:region");
                Cache::forget("user:{$chatId}:branch");
    
                $keyboard = Keyboard::make()
                    ->setResizeKeyboard(true)
                    ->setOneTimeKeyboard(false)
                    ->row([
                        Keyboard::button(['text' => '✍️ Наўбетке жазылыў']),
                        Keyboard::button(['text' => '📋 Наўбетти тексериў']),
                    ]);
    
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Керекли әмелди сайлаң:',
                    'reply_markup' => $keyboard,
                ]);
            }
            if ($text === '/start') {
                $command = new StartCommand();
                $command->makeTelegram($telegram);
                $command->makeUpdate($update);
                $command->handle();
                return;
            }
            // 2. Navbatni korish
            if ($text === '📋 Наўбетти тексериў') {
    
                $customer = Customer::where('telegram_user_id','=',$chatId)->first();
                $myQueue=GayApplication::where('customer_id',$customer->id)->where('status_id',2)->latest()->first();
                
                if($myQueue){
                    $lastQueue = GayApplication::where('branch_id',$myQueue->branch_id)->whereHas('status', function (Builder $query) {
                        $query->where('key', '=', 'completed');
                    })->latest()->first();
    
                    $lastQueueNumber = $lastQueue?->queueNumber?->queue_number ?? 0;
        
                    $lastQueueText=$lastQueueNumber > 0 ? "✅ Ақырғы кирген наўбет:  № $lastQueueNumber": "Еле ешким тестке кирген жок";
                
                    $myQueueNumber=$myQueue->queueNumber->queue_number;
    
                    $waitingCount = GayApplication::where('branch_id', $myQueue->branch_id)
                    ->whereHas('status', function (Builder $query) {
                        $query->where('key', '=','active');
                    })->whereHas('queueNumber', function (Builder $query) use ($lastQueueNumber, $myQueueNumber) {
                        $query->where('queue_number', '>', $lastQueueNumber)
                              ->where('queue_number', '<', $myQueueNumber);
                    })->count();
                    $waiting=$waitingCount>0 ? "❇️ Сиздиң алдыңызда $waitingCount пуҳара бар": "Сиздиң алдыңызда ешким жок";
                    
                    $telegram->sendMessage([
                        'chat_id' => $chatId, // Foydalanuvchining chat_id sini olish
                        'text' => "<blockquote>📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n❗️Тест тапсырыу орныныз: $myQueue->branch_name\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,3,5 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy</blockquote>",
                        'parse_mode' => 'HTML'
                    ]);
                }else{
                    $active='⭕️ Сизде актив наубет жок';
                    $telegram->sendMessage([
                        'chat_id' => $chatId, // Foydalanuvchining chat_id sini olish
                        'text' => "<blockquote>📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n$active\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,3,5 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy </blockquote>",
                        'parse_mode' => 'HTML'
                    ]);
                }
            }
    
            if ($text === '✍️ Наўбетке жазылыў') {
                Cache::put("user:{$chatId}:step", "region", 600);
                
                $regions = Region::pluck('name')->toArray();
    
                $buttons = collect($regions)->chunk(2)->map(function ($chunk) {
                    return $chunk->values()->all();
                })->values()->all();
    
                $keyboard = Keyboard::make()
                    ->setKeyboard($buttons)
                    ->setResizeKeyboard(true)
                    ->setOneTimeKeyboard(true);
            
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Прописка туўылған районыңызды сайлаң 🔰\n\n ⭕️ Итибар бериң сиз сайлаған район бойынша имтиханды өзиңизге жақын орында тапсырасыз. Паспорт пропискасы бойынша районды дурыс сайлаң ❗️",
                    'reply_markup' => $keyboard
                ]);
            }
    
            if ($step === 'region') {
                $reg = Region::where('name', $text)->first();
    
                if (!$reg) {
                    return $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => '❌ Бул регио табылмады. Илтимас, тизимнен берилген регионлардан бирин сайлан.',
                    ]);
                }
                $branch = $reg->branch->first();
                Cache::put("user:{$chatId}:region", $reg->id, 600);
                Cache::put("user:{$chatId}:branch", $branch->id, 600);
                Cache::put("user:{$chatId}:step", "awaiting_name", 600);
    
    
                $keyboard=Keyboard::remove();
    
                return $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Фамилия атыңызды толық киритин ( Нокисбаев Оралбай)',
                    'reply_markup' => $keyboard,
                ]);
            }
            if ($step === 'awaiting_name') {
                if (!preg_match('/^([\p{L}]{2,}\s){1,}[\p{L}]{2,}$/u', $text)) {
                    return $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => '❌ Фамилия атыңызды толық киритин ( Нокисбаев Оралбай)',
                    ]);
                }
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
                    'text' => '📷 Айдаўшылық гүўалығын алыў ушын төленген квитанция, экзамен билети ямаса басқа тастыйықлаўшы хужжетти  жибериң. Паспорт ид карта жибермен❌',
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
                if ($customer && $customer->full_name === null) {
                    $customer->update([
                        'region_id' => $region,
                        'branch_id' => $branch,
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
                        'region_id' => $region,
                        'branch_id' => $branch,
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
                    'region_id' => $region,
                    'branch_id' => $branch,
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
                Cache::forget("user:{$chatId}:region");
                Cache::forget("user:{$chatId}:branch");
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
                Cache::forget("user:{$chatId}:region");
                Cache::forget("user:{$chatId}:branch");
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
        } catch (\Throwable $th) {

            $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
            $telegram->sendMessage([
                'chat_id' => env('TELEGRAM_MY_CHAT_ID'),
                'text' => $th->getMessage() . ' on line ' . $th->getLine() . ' in ' . $th->getFile()
            ]);
        }
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
