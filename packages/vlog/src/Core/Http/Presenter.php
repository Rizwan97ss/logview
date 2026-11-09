<?php

namespace Rss\Vlog\Core\Http;

use DateTimeInterface;
use Rss\Vlog\Core\Entry\LogEntry;
use Rss\Vlog\Core\Entry\StackFrame;
use Rss\Vlog\Core\Files\LogFile;
use Rss\Vlog\Core\Security\Redactor;

/**
 * Converts log files and entries to the arrays the API sends as JSON, hiding
 * secrets in entries on the way.
 */
final class Presenter
{
    private Redactor $redactor;

    public function __construct(Redactor $redactor)
    {
        $this->redactor = $redactor;
    }

    /**
     * @return array<string, mixed>
     */
    public function file(LogFile $file): array
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
    public function entry(LogEntry $entry): array
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
            'message' => $this->redactor->text($entry->message()),
            'context' => $this->redactor->context($entry->context()),
            'stack' => array_map(function (StackFrame $frame): array {
                $call = $frame->call();

                return [
                    'index' => $frame->index(),
                    'file' => $frame->file(),
                    'line' => $frame->line(),
                    'call' => $call === null ? null : $this->redactor->text($call),
                ];
            }, $entry->stackFrames()),
        ];
    }

    /**
     * @param  list<LogEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public function entries(array $entries): array
    {
        return array_map([$this, 'entry'], $entries);
    }
}
