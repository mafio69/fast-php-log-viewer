<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

trait ErrorContextTrait
{
    private function getLastErrorMessage(): string
    {
        $error = error_get_last();
        if ($error === null) {
            return '';
        }
        return sprintf(' [PHP Error: %s in %s:%d]', $error['message'], $error['file'], $error['line']);
    }
}
