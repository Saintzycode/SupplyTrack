<?php
/**
 * includes/pdf.php
 * Minimal, dependency-free PDF writer (own "SimplePdf" class) - no Composer,
 * no external library. Produces A4-landscape documents with filled
 * rectangles, coloured text, lines, repeated page footers and multi-page
 * content using the core Helvetica fonts (WinAnsi encoding).
 *
 * Coordinates are given top-left origin (y grows downward) and converted
 * to PDF space internally.
 */
final class SimplePdf
{
    private float $w;
    private float $h;

    /** @var string[] One PDF content stream per page. */
    private array $pages = [];
    private int $current = -1;

    private ?string $footerLeft  = null;
    private ?string $footerRight = null;
    private ?string $footerColor = null;

    public function __construct(float $width = 841.89, float $height = 595.28)
    {
        $this->w = $width;
        $this->h = $height;
        $this->addPage();
    }

    public function width(): float  { return $this->w; }
    public function height(): float { return $this->h; }

    public function pageCount(): int { return count($this->pages); }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    /** Footer text for every page; {page} and {total} are replaced. */
    public function setFooter(?string $left, ?string $right, ?string $color = null): void
    {
        $this->footerLeft  = $left;
        $this->footerRight = $right;
        $this->footerColor = $color;
    }

    /** White-on-dark helper: filled rectangle. */
    public function rectFill(float $x, float $y, float $width, float $height, string $color): void
    {
        [$r, $g, $b] = $this->rgb($color);
        $this->pages[$this->current] .= sprintf(
            "%.4F %.4F %.4F rg %.2F %.2F %.2F %.2F re f\n",
            $r, $g, $b, $x, $this->h - ($y + $height), $width, $height
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#000000', float $lineWidth = 0.5): void
    {
        [$r, $g, $b] = $this->rgb($color);
        $this->pages[$this->current] .= sprintf(
            "%.4F %.4F %.4F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $r, $g, $b, $lineWidth, $x1, $this->h - $y1, $x2, $this->h - $y2
        );
    }

    public function text(float $x, float $y, string $s, float $size = 10, ?string $color = null, bool $bold = false): void
    {
        $this->pages[$this->current] .= $this->cmdText($x, $y, $s, $size, $color, $bold);
    }

    /** Approximate rendered width of a string at the given size. */
    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $s = $this->encode($s);
        $w = 0.0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $w += $this->charWidth($s[$i]);
        }
        return $w * $size * ($bold ? 1.03 : 1.0);
    }

    /** Shorten a string with an ellipsis so it fits $maxWidth. */
    public function ellipsize(string $s, float $maxWidth, float $size, bool $bold = false): string
    {
        if ($this->textWidth($s, $size, $bold) <= $maxWidth) return $s;
        $ell = '...';
        $ellW = $this->textWidth($ell, $size, $bold);
        $out = '';
        $len = mb_strlen($s, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $next = mb_substr($s, 0, $i + 1, 'UTF-8');
            if ($this->textWidth($next, $size, $bold) + $ellW > $maxWidth) break;
            $out = $next;
        }
        return rtrim($out) . $ell;
    }

    public function output(): string
    {
        $total = count($this->pages);
        if ($this->footerLeft !== null || $this->footerRight !== null) {
            for ($i = 0; $i < $total; $i++) {
                $this->pages[$i] .= $this->footerContent($i + 1, $total);
            }
        }

        $fontReg = 3;
        $fontBold = 4;
        $firstPageObj = 5;

        $objects = [];
        $kids = [];
        foreach ($this->pages as $i => $content) {
            $pageObj    = $firstPageObj + 2 * $i;
            $contentObj = $pageObj + 1;
            $kids[] = $pageObj . ' 0 R';
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->num($this->w) . ' ' . $this->num($this->h)
                . '] /Resources << /Font << /F1 ' . $fontReg . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents '
                . $contentObj . ' 0 R >>';
            $objects[$contentObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($out);
        $maxNum = max(array_keys($objects));
        $count = $maxNum + 1;
        $out .= "xref\n0 " . $count . "\n";
        $out .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $maxNum; $n++) {
            $out .= isset($offsets[$n])
                ? sprintf("%010d 00000 n \n", $offsets[$n])
                : "0000000000 65535 f \n";
        }
        $out .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

        return $out;
    }

    /* -------------------------------------------------------------- */

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private function cmdText(float $x, float $y, string $s, float $size, ?string $color, bool $bold): string
    {
        $s = $this->encode($s);
        if ($s === '') return '';
        [$r, $g, $b] = $this->rgb($color);
        return sprintf(
            "BT /%s %.2F Tf %.4F %.4F %.4F rg %.2F %.2F Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1', $size, $r, $g, $b, $x, $this->h - $y, $s
        );
    }

    private function footerContent(int $page, int $total): string
    {
        $y = $this->h - 22;
        $cmd = '';
        $cmd .= sprintf(
            "0.8863 0.9098 0.9451 RG 0.5 w %.2F %.2F m %.2F %.2F l S\n",
            40.0, 32.0, $this->w - 40.0, 32.0
        );
        $left  = str_replace(['{page}', '{total}'], [(string)$page, (string)$total], (string)$this->footerLeft);
        $right = str_replace(['{page}', '{total}'], [(string)$page, (string)$total], (string)$this->footerRight);
        if ($left !== '') {
            $cmd .= $this->cmdText(40.0, $y, $left, 8, $this->footerColor ?? '#475569', false);
        }
        if ($right !== '') {
            $rw = $this->textWidth($right, 8, false);
            $cmd .= $this->cmdText($this->w - 40.0 - $rw, $y, $right, 8, $this->footerColor ?? '#475569', false);
        }
        return $cmd;
    }

    /** @return array{0:float,1:float,2:float} */
    private function rgb(?string $color): array
    {
        if ($color === null || $color === '') return [0.0, 0.0, 0.0];
        $hex = ltrim($color, '#');
        if (strlen($hex) !== 6) return [0.0, 0.0, 0.0];
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /** UTF-8 -> single-byte (WinAnsi) and PDF string escaping. */
    private function encode(string $s): string
    {
        if ($s === '') return '';
        $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        if ($conv === false) {
            $conv = preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $conv);
    }

    /** Rough Helvetica advance width (in em units) per character. */
    private function charWidth(string $ch): float
    {
        if ($ch === ' ') return 0.278;
        if (str_contains('iljtfrI|.,;:!\'`', $ch)) return 0.278;
        if (str_contains('mwMW', $ch)) return 0.833;
        if (str_contains('ABCDEFGHKNOPQRSTUVXYZ', $ch)) return 0.667;
        if ($ch >= '0' && $ch <= '9') return 0.556;
        return 0.556;
    }
}
