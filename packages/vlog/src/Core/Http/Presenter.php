<?php

namespace Rss\Vlog\Core\Http;

use DateTimeInterface;
use Rss\Vlog\Core\Entry\LogEntry;
use Rss\Vlog\Core\Entry\StackFrame;
use Rss\Vlog\Core\Files\LogFile;

/**
 * Converts log files and entries to the arrays the API sends as JSON.
 */
final class Presenter
{
    /**
     * @return array<string, mixed>
     */
    public static function file(LogFile $file): array
    {
        return [
            'id' => $file->id(),
            'name' => $file->name(),
            'path' => $file->relativePath(),
            'folder' => $file->folder(),
            'size' => $file->size(),
            'modified_at' => date(DateTimeInterface::ATOM, $file->modifiedAt()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function entry(LogEntry $entry): array
    {
        $time = $entry->time();

        return [
            'offset' => $entry->offset(),
            'length' => $entry->length(),
            'truncated' => $entry->isTruncated(),
            'level' => $entry->level(),
            'raw_level' => $entry->rawLevel(),
            'time' => $time === null ? null : $time->format(DateTimeInterface::ATOM),
            'channel' => $entry->channel(),
            'message' => $entry->message(),
            'context' => $entry->context(),
            'stack' => array_map(function (StackFrame $frame): array {
                return [
                    'index' => $frame->index(),
                    'file' => $frame->file(),
                    'line' => $frame->line(),
                    'call' => $frame->call(),
                ];
            }, $entry->stackFrames()),
        ];
    }

    /**
     * @param  list<LogEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public static function entries(array $entries): array
    {
        return array_map([self::class, 'entry'], $entries);
    }
}
