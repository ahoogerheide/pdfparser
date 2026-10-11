<?php declare(strict_types=1);

namespace PrinsFrank\PdfParser\Document\Dictionary\DictionaryValue\TextString;

use Override;
use PrinsFrank\PdfParser\Document\Dictionary\DictionaryValue\DictionaryValue;
use PrinsFrank\PdfParser\Document\Encoding\PDFDocEncoding;
use PrinsFrank\PdfParser\Exception\ParseFailureException;

readonly class LiteralStringValue implements DictionaryValue {
    public function __construct(
        public string $value,
    ) {}

    /** @throws ParseFailureException */
    public function getText(): string {
        $binaryValue = $this->getBinaryString();
        if (str_starts_with($binaryValue, "\xFE\xFF")) {
            return mb_convert_encoding(substr($binaryValue, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_starts_with($binaryValue, "\xFF\xFE")) {
            return mb_convert_encoding(substr($binaryValue, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($binaryValue, "\xEF\xBB\xBF")) {
            return substr($binaryValue, 3);
        }

        return PDFDocEncoding::textToUnicode($binaryValue);
    }

    /** @throws ParseFailureException */
    public function getBinaryString(): string {
        $value = $this->value;
        $length = strlen($value);
        $decoded = '';
        for ($index = 0; $index < $length; $index++) {
            if ($value[$index] !== '\\') {
                $decoded .= $value[$index];

                continue;
            }

            if ($index + 1 >= $length) {
                $decoded .= '\\';

                break;
            }

            $next = $value[++$index];
            if ($next === "\r") {
                if (($value[$index + 1] ?? '') === "\n") {
                    $index++;
                }

                continue;
            }
            if ($next === "\n") {
                continue;
            }

            if ($next >= '0' && $next <= '7') {
                $octal = $next;
                for ($digits = 1; $digits < 3; $digits++) {
                    $candidate = $value[$index + 1] ?? '';
                    if ($candidate < '0' || $candidate > '7') {
                        break;
                    }
                    $octal .= $candidate;
                    $index++;
                }
                $decoded .= chr((int) octdec($octal));

                continue;
            }

            $decoded .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\f",
                '(', ')', '\\' => $next,
                default => '\\' . $next,
            };
        }

        return $decoded;
    }

    /** @return list<int> */
    public function getCodePoints(): array {
        $codePoints = [];
        foreach (str_split($this->getBinaryString()) as $char) {
            $codePoints[] = ord($char);
        }

        return $codePoints;
    }

    #[Override]
    public static function fromValue(string $valueString): ?self {
        $valueString = trim($valueString);
        if (str_starts_with($valueString, '(') === false
            || str_ends_with($valueString, ')') === false) {
            return null;
        }

        return new self(substr($valueString, 1, -1));
    }
}
