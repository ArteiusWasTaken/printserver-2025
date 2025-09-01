<?php

namespace App\Services;

use DateTime;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

class ManifiestoService
{
    /**
     * @throws Throwable
     */
    public static function impresion(string $ip, string $paqueteria, array $guias, $reimpresion, ?string $logoPath = 'img/omg.png', ): void
    {
        $printer = null;

        // intentar
        $encode = fn(string $t) => $t;

        try {
            $connector = new NetworkPrintConnector($ip, 9100);
            $printer = new Printer($connector);

            $titulo = $reimpresion ? "Manifiesto (Reimpresion)" : "Manifiesto";
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if ($logoPath && file_exists($logoPath)) {
                try {
                    $img = EscposImage::load($logoPath, false);
                    $printer->graphics($img);
                } catch (Throwable) {
                }
            }

            $printer->feed(2);
            $printer->text($titulo . "\n");
            $printer->text($encode($paqueteria) . "\n\n");

            $printer->setJustification();
            $printer->text($encode("Guías") . "\n");
            $printer->text(count($guias) . "\n");
            $printer->text(str_repeat('-', 42) . "\n");

            foreach ($guias as $g) {
                $printer->text($encode((string)$g) . "\n");
            }

            $printer->feed(4);
            $printer->text(str_repeat('-', 42) . "\n");
            $printer->text($encode("Recibí") . "\n\n");

            $date = (new DateTime('now', new \DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($date . "\n\n");

            $printer->cut();
            $printer->close();

        } catch (Throwable $e) {
            if ($printer) {
                try {
                    $printer->close();
                } catch (Throwable) {
                }
            }
            throw $e;
        }
    }
}
