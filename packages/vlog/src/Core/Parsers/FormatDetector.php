<?php

namespace Rss\Vlog\Core\Parsers;

use Rss\Vlog\Core\Exceptions\LogFileException;
use Rss\Vlog\Core\Reader\LogFile;

/**
 * Chooses the parser for a log file by sampling its first lines.
 */
final class FormatDetector
{
    /**
     * The most bytes read from the start of a file to detect its format.
     */
    private const SAMPLE_BYTES = 65536;

    /**
     * @var list<Parser>
     */
    private array $parsers;

    /**
     * @param  list<Parser>|null  $parsers  The parsers to choose from, in order of preference.
     */
    public function __construct(?array $parsers = null)
    {
        $this->parsers = $parsers ?? [
            new LaravelParser,
            new CodeIgniterParser,
            new JsonLinesParser,
        ];
    }

    /**
     * Choose the parser for a file, or the plain text parser when no other
     * parser recognizes it.
     *
     * @throws LogFileException
     */
    public function detect(string $path): Parser
    {
        $handle = LogFile::open($path);

        try {
            $sample = (string) fread($handle, self::SAMPLE_BYTES);
        } finally {
            fclose($handle);
        }

        $lines = preg_split('/\R/', $sample) ?: [];

        // The sample may end partway through a line, which can't be trusted.
        if (strlen($sample) === self::SAMPLE_BYTES && count($lines) > 1) {
            array_pop($lines);
        }

        return $this->detectFromLines($lines);
    }

    /**
     * Choose the parser that recognizes the most entries in the given lines.
     *
     * @param  list<string>  $lines
     */
    public function detectFromLines(array $lines): Parser
    {
        $bestParser = null;
        $bestScore = 0;

        foreach ($this->parsers as $parser) {
            $score = 0;

            foreach ($lines as $line) {
                if ($parser->startsEntry($line)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestParser = $parser;
                $bestScore = $score;
            }
        }

        return $bestParser ?? new PlainTextParser;
    }
}
