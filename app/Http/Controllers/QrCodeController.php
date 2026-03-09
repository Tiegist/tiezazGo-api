<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\Table;
use App\Services\Qr\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class QrCodeController extends Controller
{
    public function downloadTable(Request $request, Table $table): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $table->restaurant_id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $format = strtolower((string) $request->query('format', 'png'));
        if (! in_array($format, ['png', 'svg'], true)) {
            abort(400, 'Invalid format');
        }

        $path = $format === 'svg' ? $table->qr_code_svg : $table->qr_code;

        if (! $path) {
            if ($format === 'png' && ! extension_loaded('gd')) {
                abort(501, 'PNG generation is not available (GD extension missing)');
            }

            app(QrCodeService::class)->generateForTable($table, alsoSvg: true);
            $table = $table->fresh();
            $path = $format === 'svg' ? $table->qr_code_svg : $table->qr_code;
        }

        if (! $path) {
            abort(500, 'QR code not available');
        }

        $disk = (string) config('qr.disk', 'public');
        return Storage::disk($disk)->download($path);
    }

    public function downloadRestaurantZip(Request $request, Restaurant $restaurant): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $restaurant->id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        $restaurant->loadMissing('tables');

        $disk = (string) config('qr.disk', 'public');
        $tmpDisk = Storage::disk('local');
        $tmpPath = 'tmp/qrcodes-'.$restaurant->slug.'-'.time().'.zip';

        $tmpDisk->makeDirectory('tmp');

        $zipFullPath = $tmpDisk->path($tmpPath);
        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create zip');
        }

        foreach ($restaurant->tables as $table) {
            if (! $table->qr_code) {
                app(QrCodeService::class)->generateForTable($table, alsoSvg: true);
                $table = $table->fresh();
            }

            if ($table->qr_code && Storage::disk($disk)->exists($table->qr_code)) {
                $zip->addFromString(
                    basename($table->qr_code),
                    Storage::disk($disk)->get($table->qr_code)
                );
            }

            if ($table->qr_code_svg && Storage::disk($disk)->exists($table->qr_code_svg)) {
                $zip->addFromString(
                    basename($table->qr_code_svg),
                    Storage::disk($disk)->get($table->qr_code_svg)
                );
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($tmpDisk, $tmpPath) {
            echo $tmpDisk->get($tmpPath);
            $tmpDisk->delete($tmpPath);
        }, "qrcodes-{$restaurant->slug}.zip");
    }

    public function regenerateRestaurant(Request $request, Restaurant $restaurant): \Illuminate\Http\JsonResponse
    {
        if (! $this->isAdmin($request)) {
            if ((int) $restaurant->id !== (int) $this->requireRestaurantId($request)) {
                abort(404);
            }
        }

        app(QrCodeService::class)->regenerateForRestaurant($restaurant);

        return $this->respondSuccess(null, 'QR codes regenerated successfully');
    }
}

