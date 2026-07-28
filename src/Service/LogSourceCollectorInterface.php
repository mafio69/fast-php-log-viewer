<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

/**
 * Single responsibility: gather the list of LogSource directories to inspect.
 *
 * A collector MUST NOT read the contents of a directory (that is the job of a
 * DirectoryReader such as LogScanner::scanDirectory). It returns metadata
 * about *where* to look, not *what* is inside. This separation keeps "collect"
 * and "scan" as two distinct steps callable independently by the controller.
 */
interface LogSourceCollectorInterface
{
    /**
     * @return array<int, LogSource>
     */
    public function collect(): array;
}
