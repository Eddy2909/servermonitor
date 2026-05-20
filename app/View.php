<?php
declare(strict_types=1);

namespace ModernMonitor;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $file = APP_DIR . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . $template;
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}
