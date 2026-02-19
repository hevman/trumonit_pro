<?php

namespace App\Services;

class TextDiffService
{
    /**
     * @return array{lines: list<array{type: 'equal'|'insert'|'delete', text: string}>, truncated: bool}
     */
    public function diff(string $before, string $after, int $maxLinesPerSide = 220): array
    {
        $beforeLinesInfo = $this->prepareLines($before, $maxLinesPerSide);
        $afterLinesInfo = $this->prepareLines($after, $maxLinesPerSide);

        $beforeLines = $beforeLinesInfo['lines'];
        $afterLines = $afterLinesInfo['lines'];

        $truncated = (bool) ($beforeLinesInfo['truncated'] || $afterLinesInfo['truncated']);

        $ops = $this->diffLines($beforeLines, $afterLines);

        return [
            'lines' => $ops,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  list<string>  $beforeLines
     * @param  list<string>  $afterLines
     * @return list<array{type: 'equal'|'insert'|'delete', text: string}>
     */
    private function diffLines(array $beforeLines, array $afterLines): array
    {
        $n = count($beforeLines);
        $m = count($afterLines);

        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                if ($beforeLines[$i] === $afterLines[$j]) {
                    $dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
                }
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;

        while ($i < $n && $j < $m) {
            if ($beforeLines[$i] === $afterLines[$j]) {
                $ops[] = ['type' => 'equal', 'text' => $beforeLines[$i]];
                $i++;
                $j++;
                continue;
            }

            if ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $ops[] = ['type' => 'delete', 'text' => $beforeLines[$i]];
                $i++;
                continue;
            }

            $ops[] = ['type' => 'insert', 'text' => $afterLines[$j]];
            $j++;
        }

        while ($i < $n) {
            $ops[] = ['type' => 'delete', 'text' => $beforeLines[$i]];
            $i++;
        }

        while ($j < $m) {
            $ops[] = ['type' => 'insert', 'text' => $afterLines[$j]];
            $j++;
        }

        return $ops;
    }

    /**
     * @return array{lines: list<string>, truncated: bool}
     */
    private function prepareLines(string $text, int $maxLines): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (! str_contains($text, "\n") && str_contains($text, '<') && str_contains($text, '>')) {
            $text = (string) preg_replace('/>\\s*</', ">\n<", $text);
        }

        $rawLines = explode("\n", $text);

        $lines = [];

        foreach ($rawLines as $rawLine) {
            $line = rtrim((string) $rawLine, "\t ");

            if ($line === '') {
                $lines[] = '';
                continue;
            }

            $maxLen = 140;

            if (strlen($line) <= $maxLen) {
                $lines[] = $line;
                continue;
            }

            foreach (str_split($line, $maxLen) as $chunk) {
                $lines[] = $chunk;
            }
        }

        $truncated = false;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $truncated = true;
        }

        return [
            'lines' => array_values($lines),
            'truncated' => $truncated,
        ];
    }
}

