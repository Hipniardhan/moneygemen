<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\HouseholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppController extends Controller
{
    public function demo(HouseholdService $households)
    {
        $user = $households->demoUser();
        Auth::login($user);
        request()->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/masuk');
    }

    public function subscribe(Request $request): JsonResponse
    {
        // akun demo dipakai bersama banyak orang: jangan simpan langganan push
        abort_if($request->user()->is_demo, 403, 'Akun demo tidak bisa mengaktifkan notifikasi.');

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000', function ($attr, $value, $fail) {
                // Server nanti mengirim request ke endpoint ini. Tanpa pembatasan, user bisa
                // mengarahkannya ke alamat internal (SSRF). Hanya terima layanan push resmi browser.
                $parts = parse_url((string) $value);
                $host = strtolower($parts['host'] ?? '');
                $allowed = ['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];
                $ok = ($parts['scheme'] ?? '') === 'https' && ! isset($parts['port']) && ! isset($parts['user'])
                    && collect($allowed)->contains(fn ($d) => $host === $d || str_ends_with($host, '.' . $d));
                $ok || $fail('Layanan notifikasi tidak dikenali.');
            }],
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => 'required|string|max:2000']);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
