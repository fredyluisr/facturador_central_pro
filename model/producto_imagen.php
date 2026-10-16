<?php

/** Normaliza y comprime las imágenes de productos antes de publicarlas. */
final class ProductoImagen
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_PIXELS = 24000000;
    public const MAX_EDGE = 1000;
    public const WEBP_QUALITY = 82;

    public static function convertir(string $origen, string $destino): void
    {
        $peso = @filesize($origen);
        if (!$peso || $peso > self::MAX_BYTES) {
            throw new InvalidArgumentException('La imagen debe pesar entre 1 byte y 10 MB.');
        }
        $info = @getimagesize($origen);
        $permitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!$info || !in_array((int)$info[2], $permitidos, true)) {
            throw new InvalidArgumentException('Usa una imagen JPG, PNG, GIF o WebP válida.');
        }
        $pixeles = (int)$info[0] * (int)$info[1];
        if ($info[0] < 1 || $info[1] < 1 || $pixeles > self::MAX_PIXELS) {
            throw new InvalidArgumentException('La resolución de la imagen es demasiado grande.');
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new RuntimeException('El servidor necesita GD con soporte WebP para optimizar imágenes.');
        }
        $autoload = dirname(__DIR__).'/vendor/autoload.php';
        if (!is_file($autoload)) throw new RuntimeException('Faltan las dependencias de imágenes del proyecto.');
        require_once $autoload;

        $manager = new \Intervention\Image\ImageManager(['driver'=>'gd']);
        $imagen = null;
        try {
            $imagen = $manager->make($origen);
            if ((int)$info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) $imagen->orientate();
            $imagen->resize(self::MAX_EDGE, self::MAX_EDGE, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $imagen->save($destino, self::WEBP_QUALITY, 'webp');
            if (!is_file($destino) || filesize($destino) < 1) throw new RuntimeException('No se pudo crear la imagen optimizada.');
        } catch (\Intervention\Image\Exception\NotReadableException $e) {
            throw new InvalidArgumentException('La imagen está dañada o no se puede leer.', 0, $e);
        } finally {
            if ($imagen) $imagen->destroy();
        }
    }
}
