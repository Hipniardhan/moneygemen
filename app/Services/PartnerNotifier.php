<?php

namespace App\Services;

use App\Models\PartnerNotification;
use App\Models\PushSubscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PartnerNotifier
{
    /** Beritahu pasangan tentang aksi transaksi: created | updated | deleted. */
    public function transaction(User $actor, string $action, Transaction $t): void
    {
        $partner = $actor->partner();
        if (! $partner) {
            return;
        }

        $verb = ['created' => 'mencatat', 'updated' => 'mengubah', 'deleted' => 'menghapus'][$action];
        $kind = $t->type === 'income' ? 'pemasukan' : 'pengeluaran';
        $cat = $t->category?->name ?? '-';

        $this->send($partner, $actor, "tx_$action",
            "{$actor->name} {$verb} {$kind} · " . Rupiah::format($t->amount) . " · {$cat}");
    }

    public function system(User $to, User $actor, string $type, string $message): void
    {
        $this->send($to, $actor, $type, $message);
    }

    private function send(User $to, User $actor, string $type, string $message): void
    {
        PartnerNotification::create([
            'user_id' => $to->id,
            'actor_id' => $actor->id,
            'type' => $type,
            'message' => $message,
        ]);

        $this->push($to, $message);
    }

    private function push(User $to, string $message): void
    {
        $public = config('webpush.public_key');
        $private = config('webpush.private_key');
        $subs = PushSubscription::where('user_id', $to->id)->get();

        if (! $public || ! $private || $subs->isEmpty() || ! extension_loaded('gmp')) {
            return;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => $public,
                'privateKey' => $private,
            ]]);

            $payload = json_encode(['title' => 'MoneyGemen', 'body' => $message, 'url' => url('/')]);

            foreach ($subs as $s) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $s->endpoint,
                    'publicKey' => $s->p256dh,
                    'authToken' => $s->auth,
                ]), $payload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                }
            }
        } catch (\Throwable $e) {
            // push tidak boleh menggagalkan pencatatan transaksi
            Log::warning('Web push gagal: ' . $e->getMessage());
        }
    }
}
