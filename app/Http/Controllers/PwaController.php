<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WebPushService;
use Illuminate\Http\Request;

/** The site as an installable phone app: manifest, icons, and turning phone notifications on or off. */
class PwaController extends Controller
{
    public function manifest()
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) Setting::get('theme_color')) ? Setting::get('theme_color') : '#1b6b50';

        return response()->json([
            'name' => Setting::get('center_name'),
            'short_name' => Setting::get('brand_title') ?: 'غراس',
            'description' => Setting::get('center_about'),
            'lang' => 'ar',
            'dir' => 'rtl',
            'start_url' => route('dashboard', [], false).'?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => $color,
            'icons' => [
                ['src' => route('pwa.icon', 192, false).'?v='.$this->version(), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', 512, false).'?v='.$this->version(), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', 512, false).'?v='.$this->version(), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The center's logo centred on a white square, made once per logo and size. */
    public function icon(string $size)
    {
        $size = (int) $size;
        $file = storage_path('framework/cache/app-icon-'.$size.'-'.$this->version().'.png');
        if (! is_file($file)) {
            $logo = Setting::get('center_logo');
            $src = $logo && is_file(public_path($logo)) ? public_path($logo) : public_path('images/logo.png');
            $png = $this->squareIcon($src, $size);
            if ($png === null) {
                return response()->file($src);
            }
            @file_put_contents($file, $png);
        }

        return response()->file($file, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    public function subscribe(Request $request, WebPushService $push)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:20'],
        ]);
        $push->subscribe($request->user(), $data, (string) $request->userAgent());

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request, WebPushService $push)
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        $push->unsubscribe($request->input('endpoint'));

        return response()->json(['ok' => true]);
    }

    private function version(): string
    {
        return substr(md5((string) Setting::get('center_logo').'|'.Setting::get('theme_color')), 0, 8);
    }

    private function squareIcon(string $src, int $size): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! ($img = @imagecreatefromstring((string) file_get_contents($src)))) {
            return null;
        }
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        // Leave a margin so the logo is not cut when the phone rounds or masks the icon.
        $box = (int) round($size * 0.74);
        [$w, $h] = [imagesx($img), imagesy($img)];
        $scale = min($box / $w, $box / $h);
        [$nw, $nh] = [(int) round($w * $scale), (int) round($h * $scale)];
        imagecopyresampled($canvas, $img, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }
}
