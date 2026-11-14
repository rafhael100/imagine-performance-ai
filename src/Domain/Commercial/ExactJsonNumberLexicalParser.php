<?php
declare(strict_types=1);

namespace Imagine\Performance\Commercial;

use InvalidArgumentException;
use JsonException;
use stdClass;

/**
 * Preserves the EXACT JSON numeral lexeme for financial parsing, without
 * binary float conversion and without regex-scanning inside quoted strings.
 * Small, depth/bytes/node-bounded JSON decoder for test-only Ads reports.
 */
final class ExactJsonNumberLiteral
{
    public function __construct(public readonly string $text) {}
}

final class ExactJsonNumberLexicalParser
{
    private int $offset = 0;
    private int $nodes = 0;
    private int $length;

    private function __construct(private readonly string $raw)
    {
        $this->length = strlen($raw);
    }

    public static function decode(string $raw): mixed
    {
        if ($raw === '' || strlen($raw) > 65536) {
            throw new InvalidArgumentException('JSON response outside offline size limit.');
        }
        $parser = new self($raw);
        $value = $parser->value(0);
        $parser->whitespace();
        if ($parser->offset !== $parser->length) {
            throw new InvalidArgumentException('Trailing tokens after JSON document.');
        }
        return $value;
    }

    private function value(int $depth): mixed
    {
        if ($depth > 12 || ++$this->nodes > 2000) {
            throw new InvalidArgumentException('JSON nesting or node limit exceeded.');
        }
        $this->whitespace();
        if ($this->offset >= $this->length) {
            throw new InvalidArgumentException('Truncated JSON value.');
        }
        $char = $this->raw[$this->offset];
        if ($char === '"') {
            return $this->string();
        }
        if ($char === '{') {
            $this->offset++;
            $object = new stdClass();
            $this->whitespace();
            if ($this->accept('}')) return $object;
            do {
                $this->whitespace();
                if ($this->offset >= $this->length || $this->raw[$this->offset] !== '"') {
                    throw new InvalidArgumentException('JSON object member name missing.');
                }
                $key = $this->string();
                if (property_exists($object, $key)) {
                    throw new InvalidArgumentException('Duplicate JSON object property refused.');
                }
                $this->whitespace();
                $this->expect(':');
                $object->{$key} = $this->value($depth + 1);
                $this->whitespace();
                if ($this->accept('}')) return $object;
                $this->expect(',');
            } while (true);
        }
        if ($char === '[') {
            $this->offset++;
            $arr = [];
            $this->whitespace();
            if ($this->accept(']')) return $arr;
            do {
                $arr[] = $this->value($depth + 1);
                $this->whitespace();
                if ($this->accept(']')) return $arr;
                $this->expect(',');
            } while (true);
        }
        foreach (['true' => true, 'false' => false, 'null' => null] as $token => $literal) {
            if (substr($this->raw, $this->offset, strlen($token)) === $token) {
                $this->offset += strlen($token);
                return $literal;
            }
        }
        if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+\-]?[0-9]+)?/D',
            $this->raw, $matches, 0, $this->offset) === 1) {
            $this->offset += strlen($matches[0]);
            return new ExactJsonNumberLiteral($matches[0]);
        }
        throw new InvalidArgumentException('Invalid JSON number or keyword.');
    }

    private function string(): string
    {
        $begin = $this->offset;
        $this->expect('"');
        $escape = false;
        while ($this->offset < $this->length) {
            $char = $this->raw[$this->offset++];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                try {
                    $decoded = json_decode(
                        substr($this->raw, $begin, $this->offset - $begin),
                        true, 8, JSON_THROW_ON_ERROR
                    );
                } catch (JsonException) {
                    throw new InvalidArgumentException('Invalid UTF-8 or JSON string escape.');
                }
                if (!is_string($decoded)) {
                    throw new InvalidArgumentException('Invalid JSON string token.');
                }
                return $decoded;
            }
        }
        throw new InvalidArgumentException('Unterminated JSON string.');
    }

    private function whitespace(): void
    {
        while ($this->offset < $this->length &&
            str_contains(" \t\n\r", $this->raw[$this->offset])) {
            $this->offset++;
        }
    }

    private function accept(string $char): bool
    {
        if ($this->offset < $this->length &&
            $this->raw[$this->offset] === $char) {
            $this->offset++;
            return true;
        }
        return false;
    }

    private function expect(string $char): void
    {
        if (!$this->accept($char)) {
            throw new InvalidArgumentException('Unexpected JSON separator.');
        }
    }
}
