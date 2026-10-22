<?php

namespace App\Utils;

use setasign\Fpdi\Fpdi;

/**
 * Lớp mở rộng FPDI hỗ trợ xoay trang theo góc (90, 180, 270 độ)
 */
class RotatableFpdi extends Fpdi
{
    protected float $angle = 0;

    public function Rotate(float $angle, float $x = -1, float $y = -1): void
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $rad = $angle * M_PI / 180;
            $c = cos($rad);
            $s = sin($rad);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function _endpage(): void
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}
