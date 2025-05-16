<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\GayApplication;
use App\Models\QueueNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\Api;

class ActiveSendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $gayApplicationId;
    protected $userId;

    public function __construct(int $gayApplicationId, int $userId)
    {
        $this->gayApplicationId = $gayApplicationId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $record = GayApplication::find($this->gayApplicationId);
        if (!$record) return;

        $record->update(['status_id' => 2]); // 2 - active

        $lastQueueNumber = QueueNumber::max('queue_number') ?? 0;
        $myQueueNumber = $lastQueueNumber + 1;

        $queue = QueueNumber::create([
            'user_id' => $this->userId,
            'customer_id' => $record->customer_id,
            'gay_application_id' => $record->id,
            'branch_id' => $record->branch_id,
            'queue_number' => $myQueueNumber,
        ]);

        // Telegramga yuborish
        $customer = Customer::find($record->customer_id);
        if (!$customer) return;

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

        // $lastQueueNumber = QueueNumber::where('branch_id', $record->branch_id)->max('queue_number');

        $lastQueue = GayApplication::where('branch_id', $record->branch_id)
                            ->whereHas('status', function (Builder $query) {
                                $query->where('key', '=', 'completed');
                            })->latest()->first();
        $lastQueueNumber = $lastQueue?->queueNumber?->queue_number ?? 0;
        $waitingCount = GayApplication::whereHas('status', fn ($q) => $q->where('key', 'active'))
            ->whereHas('queueNumber', function ($q) use ($lastQueueNumber, $myQueueNumber) {
                $q->where('queue_number', '>', $lastQueueNumber)
                  ->where('queue_number', '<', $myQueueNumber);
            })->count();

        $waiting = $waitingCount > 0
            ? "❇️ Сиздиң алдыңызда $waitingCount пуҳара бар"
            : "Сиздиң алдыңызда ешким жок";

        $lastQueueText = $lastQueueNumber > 0
            ? "✅ Ақырғы кирген наўбет:  № $lastQueueNumber"
            : "Еле ешким тестке кирген жок";

        $telegram->sendMessage([
            'chat_id' => $customer->telegram_user_id,
            'text' => "<blockquote> 📱 Телефон:$customer->phone_number\n👤 ФИО:$customer->full_name\n🆔 Паспорт:$customer->passport\n\n\n⭕️ Сиздиң наўбет:  № $myQueueNumber\n\n$lastQueueText\n$waiting\n\nТест тапсырыу орныныз: $record->branch_name\n\nКүнине орташа 300-400 пуҳара имтихан тапсырыўга улгереди !\n\nИмтиҳанлар  саат 09:00 – 18:00  , хәптениң 1,3,5 күнлери болып өтеди \n\nЖаңалықлардан хабардар болыў ушын каналға кириң\n 👉 https://t.me/+oR4I260MLxszYTAy </blockquote>",
            'parse_mode' => 'HTML',
        ]);
    }
}
