<?php

trait TCPDF_CELL
{
    /**
     * Prints a cell (rectangular area) with optional borders, background color and character string. The upper-left corner of the cell corresponds to the current position. The text can be aligned or centered. After the call, the current position moves to the right or to the next line. It is possible to put a link on the text.<br />
     * If automatic page breaking is enabled and the cell goes beyond the limit, a page break is done before outputting.
     * @param float $w Cell width. If 0, the cell extends up to the right margin.
     * @param float $h Cell height. Default value: 0.
     * @param string $txt String to print. Default value: empty string.
     * @param mixed $border Indicates if borders must be drawn around the cell. The value can be a number:<ul><li>0: no border (default)</li><li>1: frame</li></ul> or a string containing some or all of the following characters (in any order):<ul><li>L: left</li><li>T: top</li><li>R: right</li><li>B: bottom</li></ul> or an array of line styles for each border group - for example: array('LTRB' => array('width' => 2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)))
     * @param int $ln Indicates where the current position should go after the call. Possible values are:<ul><li>0: to the right (or left for RTL languages)</li><li>1: to the beginning of the next line</li><li>2: below</li></ul> Putting 1 is equivalent to putting 0 and calling Ln() just after. Default value: 0.
     * @param string $align Allows to center or align the text. Possible values are:<ul><li>L or empty string: left align (default value)</li><li>C: center</li><li>R: right align</li><li>J: justify</li></ul>
     * @param boolean $fill Indicates if the cell background must be painted (true) or transparent (false).
     * @param mixed $link URL or identifier returned by AddLink().
     * @param int $stretch font stretch mode: <ul><li>0 = disabled</li><li>1 = horizontal scaling only if text is larger than cell width</li><li>2 = forced horizontal scaling to fit cell width</li><li>3 = character spacing only if text is larger than cell width</li><li>4 = forced character spacing to fit cell width</li></ul> General font stretching and scaling values will be preserved when possible.
     * @param boolean $ignore_min_height if true ignore automatic minimum height value.
     * @param string $calign cell vertical alignment relative to the specified Y value. Possible values are:<ul><li>T : cell top</li><li>C : center</li><li>B : cell bottom</li><li>A : font top</li><li>L : font baseline</li><li>D : font bottom</li></ul>
     * @param string $valign text vertical alignment inside the cell. Possible values are:<ul><li>T : top</li><li>C : center</li><li>B : bottom</li></ul>
     * @public
     * @since 1.0
     * @see SetFont(), SetDrawColor(), SetFillColor(), SetTextColor(), SetLineWidth(), AddLink(), Ln(), MultiCell(), Write(), SetAutoPageBreak()
     */
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M')
    {
        $prev_cell_margin = $this->cell_margin;
        $prev_cell_padding = $this->cell_padding;
        $this->adjustCellPadding($border);
        if (!$ignore_min_height) {
            $min_cell_height = $this->getCellHeight($this->FontSize);
            if ($h < $min_cell_height) {
                $h = $min_cell_height;
            }
        }
        $this->checkPageBreak($h + $this->cell_margin['T'] + $this->cell_margin['B']);
        // apply text shadow if enabled
        if ($this->txtshadow['enabled']) {
            // save data
            $x = $this->x;
            $y = $this->y;
            $bc = $this->bgcolor;
            $fc = $this->fgcolor;
            $sc = $this->strokecolor;
            $alpha = $this->alpha;
            // print shadow
            $this->x += $this->txtshadow['depth_w'];
            $this->y += $this->txtshadow['depth_h'];
            $this->setFillColorArray($this->txtshadow['color']);
            $this->setTextColorArray($this->txtshadow['color']);
            $this->setDrawColorArray($this->txtshadow['color']);
            if ($this->txtshadow['opacity'] != $alpha['CA']) {
                $this->setAlpha($this->txtshadow['opacity'], $this->txtshadow['blend_mode']);
            }
            if ($this->state == 2) {
                $this->_out($this->getCellCode($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, true, $calign, $valign));
            }
            //restore data
            $this->x = $x;
            $this->y = $y;
            $this->setFillColorArray($bc);
            $this->setTextColorArray($fc);
            $this->setDrawColorArray($sc);
            if ($this->txtshadow['opacity'] != $alpha['CA']) {
                $this->setAlpha($alpha['CA'], $alpha['BM'], $alpha['ca'], $alpha['AIS']);
            }
        }
        if ($this->state == 2) {
            $this->_out($this->getCellCode($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, true, $calign, $valign));
        }
        $this->cell_padding = $prev_cell_padding;
        $this->cell_margin = $prev_cell_margin;
    }
}
