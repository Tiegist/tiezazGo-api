<?php

namespace App\Services\Qr;

use App\Models\Restaurant;
use App\Models\Table;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeService
{
    public function tableUrl(Restaurant $restaurant, int $tableNumber): string
    {
        $domain = rtrim((string) config('qr.menu_domain'), '/');
        $template = (string) config('qr.table_path_template', '{slug}/table/{table_number}');

        $path = strtr($template, [
            '{slug}' => $restaurant->slug,
            '{table_number}' => (string) $tableNumber,
        ]);

        $path = ltrim($path, '/');

        return "{$domain}/{$path}";
    }

    /**
     * Generate and persist QR images for a table.
     *
     * @return array{png?:string,svg?:string,url:string}
     */
    public function generateForTable(Table $table, bool $alsoSvg = true): array
    {
        $table->loadMissing('restaurant');
        $restaurant = $table->restaurant;

        if (! $restaurant) {
            throw new \RuntimeException('Restaurant not found for table');
        }

        $url = $this->tableUrl($restaurant, (int) $table->table_number);

        // Clean up old files (e.g., table number / slug changes)
        $this->deleteFilesForTable($table);

        $pngPath = null;
        try {
            $pngPath = $this->write($url, $restaurant, (int) $table->table_number, 'png');
        } catch (\Throwable $e) {
            // PNG generation requires GD; in environments without GD we still generate SVG.
        }

        $svgPath = null;
        if ($alsoSvg) {
            $svgPath = $this->write($url, $restaurant, (int) $table->table_number, 'svg');
        }

        $table->forceFill([
            'qr_code' => $pngPath,
            'qr_code_svg' => $svgPath,
        ])->saveQuietly();

        return array_filter([
            'url' => $url,
            'png' => $pngPath,
            'svg' => $svgPath,
        ]);
    }

    public function regenerateForRestaurant(Restaurant $restaurant): void
    {
        $restaurant->loadMissing('tables');

        foreach ($restaurant->tables as $table) {
            $this->generateForTable($table);
        }
    }

    public function deleteFilesForTable(Table $table): void
    {
        $disk = (string) config('qr.disk', 'public');

        $paths = array_filter([
            $table->qr_code,
            $table->qr_code_svg,
        ]);

        if (! $paths) {
            return;
        }

        Storage::disk($disk)->delete($paths);
    }

    private function write(string $url, Restaurant $restaurant, int $tableNumber, string $format): string
    {
        $disk = (string) config('qr.disk', 'public');
        $dir = trim((string) config('qr.directory', 'qrcodes'), '/');

        $format = strtolower($format);
        if (! in_array($format, ['png', 'svg'], true)) {
            throw new \InvalidArgumentException('Unsupported QR format');
        }

        if ($format === 'png' && ! extension_loaded('gd')) {
            throw new \RuntimeException('PNG generation requires the GD PHP extension');
        }

        $restaurantSlug = $restaurant->slug;
        $filename = "{$restaurantSlug}-table-{$tableNumber}.{$format}";
        $path = "{$dir}/{$filename}";

        [$fg, $bg] = $this->colors($restaurant);
        $ec = $this->errorCorrection();
        $size = (int) config('qr.size', 300);
        $margin = (int) config('qr.margin', 10);

        $writer = $format === 'svg' ? new SvgWriter() : new PngWriter();

        $logoPath = $restaurant->qr_logo_path;
        $logoPath = is_string($logoPath) && $logoPath !== '' ? $logoPath : null;
        if ($logoPath && ! Storage::disk($disk)->exists($logoPath)) {
            $logoPath = null;
        }

        $builder = new Builder(
            writer: $writer,
            writerOptions: [],
            validateResult: false,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $ec,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: $format === 'svg' ? RoundBlockSizeMode::None : RoundBlockSizeMode::Margin,
            foregroundColor: $fg,
            backgroundColor: $bg,
            logoPath: $logoPath ? Storage::disk($disk)->path($logoPath) : '',
            logoResizeToWidth: $logoPath ? (int) max(10, min(80, (int) round($size * 0.18))) : null,
            logoPunchoutBackground: (bool) $logoPath
        );

        $result = $builder->build();

        Storage::disk($disk)->put($path, $result->getString());

        return $path;
    }

    /**
     * @return array{0:Color,1:Color}
     */
    private function colors(Restaurant $restaurant): array
    {
        $fgHex = (string) ($restaurant->qr_foreground ?: config('qr.foreground', '#000000'));
        $bgHex = (string) ($restaurant->qr_background ?: config('qr.background', '#FFFFFF'));

        $fg = $this->hexToColor($fgHex);
        $bg = $this->hexToColor($bgHex);

        return [$fg, $bg];
    }

    private function errorCorrection(): ErrorCorrectionLevel
    {
        return match (strtolower((string) config('qr.error_correction', 'high'))) {
            'low' => ErrorCorrectionLevel::Low,
            'medium' => ErrorCorrectionLevel::Medium,
            'quartile' => ErrorCorrectionLevel::Quartile,
            default => ErrorCorrectionLevel::High,
        };
    }

    private function hexToColor(string $hex): Color
    {
        $hex = trim($hex);
        if ($hex === '') {
            return new Color(0, 0, 0);
        }

        $hex = Str::of($hex)->ltrim('#')->toString();
        if (strlen($hex) === 3) {
            $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return new Color(0, 0, 0);
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return new Color($r, $g, $b);
    }
}

