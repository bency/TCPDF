<?php

trait TCPDF_WRITE
{
    /**
     * This method prints text from the current position.<br />
     * @param float $h Line height
     * @param string $txt String to print
     * @param mixed $link URL or identifier returned by AddLink()
     * @param boolean $fill Indicates if the cell background must be painted (true) or transparent (false).
     * @param string $align Allows to center or align the text. Possible values are:<ul><li>L or empty string: left align (default value)</li><li>C: center</li><li>R: right align</li><li>J: justify</li></ul>
     * @param boolean $ln if true set cursor at the bottom of the line, otherwise set cursor at the top of the line.
     * @param int $stretch font stretch mode: <ul><li>0 = disabled</li><li>1 = horizontal scaling only if text is larger than cell width</li><li>2 = forced horizontal scaling to fit cell width</li><li>3 = character spacing only if text is larger than cell width</li><li>4 = forced character spacing to fit cell width</li></ul> General font stretching and scaling values will be preserved when possible.
     * @param boolean $firstline if true prints only the first line and return the remaining string.
     * @param boolean $firstblock if true the string is the starting of a line.
     * @param float $maxh maximum height. It should be >= $h and less then remaining space to the bottom of the page, or 0 for disable this feature.
     * @param float $wadj first line width will be reduced by this amount (used in HTML mode).
     * @param array|null $margin margin array of the parent container
     * @return mixed Return the number of cells or the remaining string if $firstline = true.
     * @public
     * @since 1.5
     */
    public function Write($h, $txt, $link = '', $fill = false, $align = '', $ln = false, $stretch = 0, $firstline = false, $firstblock = false, $maxh = 0, $wadj = 0, $margin = null)
    {
        // check page for no-write regions and adapt page margins if necessary
        list($this->x, $this->y) = $this->checkPageRegions($h, $this->x, $this->y);
        if (strlen($txt) == 0) {
            // fix empty text
            $txt = ' ';
        }
        if (!is_array($margin)) {
            // set default margins
            $margin = $this->cell_margin;
        }
        // remove carriage returns
        $s = str_replace("\r", '', $txt);
        // check if string contains arabic text
        if (preg_match(TCPDF_FONT_DATA::$uni_RE_PATTERN_ARABIC, $s)) {
            $arabic = true;
        } else {
            $arabic = false;
        }
        // check if string contains RTL text
        if ($arabic or ($this->tmprtl == 'R') or preg_match(TCPDF_FONT_DATA::$uni_RE_PATTERN_RTL, $s)) {
            $rtlmode = true;
        } else {
            $rtlmode = false;
        }
        // get a char width
        $chrwidth = $this->GetCharWidth(46); // dot character
        // get array of unicode values
        $chars = TCPDF_FONTS::UTF8StringToArray($s, $this->isunicode, $this->CurrentFont);
        // calculate maximum width for a single character on string
        $chrw = $this->GetArrStringWidth($chars, '', '', 0, true);
        array_walk($chrw, array($this, 'getRawCharWidth'));
        $maxchwidth = max($chrw);
        // get array of chars
        $uchars = TCPDF_FONTS::UTF8ArrayToUniArray($chars, $this->isunicode);
        // get the number of characters
        $nb = count($chars);
        // replacement for SHY character (minus symbol)
        $shy_replacement = 45;
        $shy_replacement_char = TCPDF_FONTS::unichr($shy_replacement, $this->isunicode);
        // widht for SHY replacement
        $shy_replacement_width = $this->GetCharWidth($shy_replacement);
        // page width
        $pw = $w = $this->w - $this->lMargin - $this->rMargin;
        // calculate remaining line width ($w)
        if ($this->rtl) {
            $w = $this->x - $this->lMargin;
        } else {
            $w = $this->w - $this->rMargin - $this->x;
        }
        // max column width
        $wmax = ($w - $wadj);
        if (!$firstline) {
            $wmax -= ($this->cell_padding['L'] + $this->cell_padding['R']);
        }
        if ((!$firstline) and (($chrwidth > $wmax) or ($maxchwidth > $wmax))) {
            // the maximum width character do not fit on column
            return '';
        }
        // minimum row height
        $row_height = max($h, $this->getCellHeight($this->FontSize));
        // max Y
        $maxy = $this->y + $maxh - max($row_height, $h);
        $start_page = $this->page;
        $i = 0; // character position
        $j = 0; // current starting position
        $sep = -1; // position of the last blank space
        $prevsep = $sep; // previous separator
        $shy = false; // true if the last blank is a soft hypen (SHY)
        $prevshy = $shy; // previous shy mode
        $l = 0; // current string length
        $nl = 0; //number of lines
        $linebreak = false;
        $pc = 0; // previous character
        // for each character
        while ($i < $nb) {
            if (($maxh > 0) and ($this->y > $maxy)) {
                break;
            }
            //Get the current character
            $c = $chars[$i];
            if ($c == 10) { // 10 = "\n" = new line
                //Explicit line break
                if ($align == 'J') {
                    if ($this->rtl) {
                        $talign = 'R';
                    } else {
                        $talign = 'L';
                    }
                } else {
                    $talign = $align;
                }
                $tmpstr = TCPDF_FONTS::UniArrSubString($uchars, $j, $i);
                if ($firstline) {
                    $startx = $this->x;
                    $tmparr = array_slice($chars, $j, ($i - $j));
                    if ($rtlmode) {
                        $tmparr = TCPDF_FONTS::utf8Bidi($tmparr, $tmpstr, $this->tmprtl, $this->isunicode, $this->CurrentFont);
                    }
                    $linew = $this->GetArrStringWidth($tmparr);
                    unset($tmparr);
                    if ($this->rtl) {
                        $this->endlinex = $startx - $linew;
                    } else {
                        $this->endlinex = $startx + $linew;
                    }
                    $w = $linew;
                    $tmpcellpadding = $this->cell_padding;
                    if ($maxh == 0) {
                        $this->setCellPadding(0);
                    }
                }
                if ($firstblock and $this->isRTLTextDir()) {
                    $tmpstr = $this->stringRightTrim($tmpstr);
                }
                // Skip newlines at the beginning of a page or column
                if (!empty($tmpstr) or ($this->y < ($this->PageBreakTrigger - $row_height))) {
                    $this->Cell($w, $h, $tmpstr, 0, 1, $talign, $fill, $link, $stretch);
                }
                unset($tmpstr);
                if ($firstline) {
                    $this->cell_padding = $tmpcellpadding;
                    return (TCPDF_FONTS::UniArrSubString($uchars, $i));
                }
                ++$nl;
                $j = $i + 1;
                $l = 0;
                $sep = -1;
                $prevsep = $sep;
                $shy = false;
                // account for margin changes
                if ((($this->y + $this->lasth) > $this->PageBreakTrigger) and ($this->inPageBody())) {
                    if ($this->AcceptPageBreak()) {
                        if ($this->rtl) {
                            $this->x -= $margin['R'];
                        } else {
                            $this->x += $margin['L'];
                        }
                        $this->lMargin += $margin['L'];
                        $this->rMargin += $margin['R'];
                    }
                }
                $w = $this->getRemainingWidth();
                $wmax = ($w - $this->cell_padding['L'] - $this->cell_padding['R']);
            } else {
                // 160 is the non-breaking space.
                // 173 is SHY (Soft Hypen).
                // \p{Z} or \p{Separator}: any kind of Unicode whitespace or invisible separator.
                // \p{Lo} or \p{Other_Letter}: a Unicode letter or ideograph that does not have lowercase and uppercase variants.
                // \p{Lo} is needed because Chinese characters are packed next to each other without spaces in between.
                if (($c != 160)
                    and (($c == 173)
                        or preg_match($this->re_spaces, TCPDF_FONTS::unichr($c, $this->isunicode))
                        or (($c == 45)
                            and ($i < ($nb - 1))
                            and @preg_match('/[\p{L}]/' . $this->re_space['m'], TCPDF_FONTS::unichr($pc, $this->isunicode))
                            and @preg_match('/[\p{L}]/' . $this->re_space['m'], TCPDF_FONTS::unichr($chars[($i + 1)], $this->isunicode))
                        )
                    )
                ) {
                    // update last blank space position
                    $prevsep = $sep;
                    $sep = $i;
                    // check if is a SHY
                    if (($c == 173) or ($c == 45)) {
                        $prevshy = $shy;
                        $shy = true;
                        if ($pc == 45) {
                            $tmp_shy_replacement_width = 0;
                            $tmp_shy_replacement_char = '';
                        } else {
                            $tmp_shy_replacement_width = $shy_replacement_width;
                            $tmp_shy_replacement_char = $shy_replacement_char;
                        }
                    } else {
                        $shy = false;
                    }
                }
                // update string length
                if ($this->isUnicodeFont() and ($arabic)) {
                    // with bidirectional algorithm some chars may be changed affecting the line length
                    // *** very slow ***
                    $l = $this->GetArrStringWidth(TCPDF_FONTS::utf8Bidi(array_slice($chars, $j, ($i - $j)), '', $this->tmprtl, $this->isunicode, $this->CurrentFont));
                } else {
                    $l += $this->GetCharWidth($c, ($i + 1 < $nb));
                }
                if (($l > $wmax) or (($c == 173) and (($l + $tmp_shy_replacement_width) >= $wmax))) {
                    if (($c == 173) and (($l + $tmp_shy_replacement_width) > $wmax)) {
                        $sep = $prevsep;
                        $shy = $prevshy;
                    }
                    // we have reached the end of column
                    if ($sep == -1) {
                        // check if the line was already started
                        if (($this->rtl and ($this->x <= ($this->w - $this->rMargin - $this->cell_padding['R'] - $margin['R'] - $chrwidth)))
                            or ((!$this->rtl) and ($this->x >= ($this->lMargin + $this->cell_padding['L'] + $margin['L'] + $chrwidth)))
                        ) {
                            // print a void cell and go to next line
                            $this->Cell($w, $h, '', 0, 1);
                            $linebreak = true;
                            if ($firstline) {
                                return (TCPDF_FONTS::UniArrSubString($uchars, $j));
                            }
                        } else {
                            // truncate the word because do not fit on column
                            $tmpstr = TCPDF_FONTS::UniArrSubString($uchars, $j, $i);
                            if ($firstline) {
                                $startx = $this->x;
                                $tmparr = array_slice($chars, $j, ($i - $j));
                                if ($rtlmode) {
                                    $tmparr = TCPDF_FONTS::utf8Bidi($tmparr, $tmpstr, $this->tmprtl, $this->isunicode, $this->CurrentFont);
                                }
                                $linew = $this->GetArrStringWidth($tmparr);
                                unset($tmparr);
                                if ($this->rtl) {
                                    $this->endlinex = $startx - $linew;
                                } else {
                                    $this->endlinex = $startx + $linew;
                                }
                                $w = $linew;
                                $tmpcellpadding = $this->cell_padding;
                                if ($maxh == 0) {
                                    $this->setCellPadding(0);
                                }
                            }
                            if ($firstblock and $this->isRTLTextDir()) {
                                $tmpstr = $this->stringRightTrim($tmpstr);
                            }
                            $this->Cell($w, $h, $tmpstr, 0, 1, $align, $fill, $link, $stretch);
                            unset($tmpstr);
                            if ($firstline) {
                                $this->cell_padding = $tmpcellpadding;
                                return (TCPDF_FONTS::UniArrSubString($uchars, $i));
                            }
                            $j = $i;
                            --$i;
                        }
                    } else {
                        // word wrapping
                        if ($this->rtl and (!$firstblock) and ($sep < $i)) {
                            $endspace = 1;
                        } else {
                            $endspace = 0;
                        }
                        // check the length of the next string
                        $strrest = TCPDF_FONTS::UniArrSubString($uchars, ($sep + $endspace));
                        $nextstr = TCPDF_STATIC::pregSplit('/' . $this->re_space['p'] . '/', $this->re_space['m'], $this->stringTrim($strrest));
                        if (isset($nextstr[0]) and ($this->GetStringWidth($nextstr[0]) > $pw)) {
                            // truncate the word because do not fit on a full page width
                            $tmpstr = TCPDF_FONTS::UniArrSubString($uchars, $j, $i);
                            if ($firstline) {
                                $startx = $this->x;
                                $tmparr = array_slice($chars, $j, ($i - $j));
                                if ($rtlmode) {
                                    $tmparr = TCPDF_FONTS::utf8Bidi($tmparr, $tmpstr, $this->tmprtl, $this->isunicode, $this->CurrentFont);
                                }
                                $linew = $this->GetArrStringWidth($tmparr);
                                unset($tmparr);
                                if ($this->rtl) {
                                    $this->endlinex = ($startx - $linew);
                                } else {
                                    $this->endlinex = ($startx + $linew);
                                }
                                $w = $linew;
                                $tmpcellpadding = $this->cell_padding;
                                if ($maxh == 0) {
                                    $this->setCellPadding(0);
                                }
                            }
                            if ($firstblock and $this->isRTLTextDir()) {
                                $tmpstr = $this->stringRightTrim($tmpstr);
                            }
                            $this->Cell($w, $h, $tmpstr, 0, 1, $align, $fill, $link, $stretch);
                            unset($tmpstr);
                            if ($firstline) {
                                $this->cell_padding = $tmpcellpadding;
                                return (TCPDF_FONTS::UniArrSubString($uchars, $i));
                            }
                            $j = $i;
                            --$i;
                        } else {
                            // word wrapping
                            if ($shy) {
                                // add hypen (minus symbol) at the end of the line
                                $shy_width = $tmp_shy_replacement_width;
                                if ($this->rtl) {
                                    $shy_char_left = $tmp_shy_replacement_char;
                                    $shy_char_right = '';
                                } else {
                                    $shy_char_left = '';
                                    $shy_char_right = $tmp_shy_replacement_char;
                                }
                            } else {
                                $shy_width = 0;
                                $shy_char_left = '';
                                $shy_char_right = '';
                            }
                            $tmpstr = TCPDF_FONTS::UniArrSubString($uchars, $j, ($sep + $endspace));
                            if ($firstline) {
                                $startx = $this->x;
                                $tmparr = array_slice($chars, $j, (($sep + $endspace) - $j));
                                if ($rtlmode) {
                                    $tmparr = TCPDF_FONTS::utf8Bidi($tmparr, $tmpstr, $this->tmprtl, $this->isunicode, $this->CurrentFont);
                                }
                                $linew = $this->GetArrStringWidth($tmparr);
                                unset($tmparr);
                                if ($this->rtl) {
                                    $this->endlinex = $startx - $linew - $shy_width;
                                } else {
                                    $this->endlinex = $startx + $linew + $shy_width;
                                }
                                $w = $linew;
                                $tmpcellpadding = $this->cell_padding;
                                if ($maxh == 0) {
                                    $this->setCellPadding(0);
                                }
                            }
                            // print the line
                            if ($firstblock and $this->isRTLTextDir()) {
                                $tmpstr = $this->stringRightTrim($tmpstr);
                            }
                            $this->Cell($w, $h, $shy_char_left . $tmpstr . $shy_char_right, 0, 1, $align, $fill, $link, $stretch);
                            unset($tmpstr);
                            if ($firstline) {
                                if ($chars[$sep] == 45) {
                                    $endspace += 1;
                                }
                                // return the remaining text
                                $this->cell_padding = $tmpcellpadding;
                                return (TCPDF_FONTS::UniArrSubString($uchars, ($sep + $endspace)));
                            }
                            $i = $sep;
                            $sep = -1;
                            $shy = false;
                            $j = ($i + 1);
                        }
                    }
                    // account for margin changes
                    if ((($this->y + $this->lasth) > $this->PageBreakTrigger) and ($this->inPageBody())) {
                        if ($this->AcceptPageBreak()) {
                            if ($this->rtl) {
                                $this->x -= $margin['R'];
                            } else {
                                $this->x += $margin['L'];
                            }
                            $this->lMargin += $margin['L'];
                            $this->rMargin += $margin['R'];
                        }
                    }
                    $w = $this->getRemainingWidth();
                    $wmax = $w - $this->cell_padding['L'] - $this->cell_padding['R'];
                    if ($linebreak) {
                        $linebreak = false;
                    } else {
                        ++$nl;
                        $l = 0;
                    }
                }
            }
            // save last character
            $pc = $c;
            ++$i;
        } // end while i < nb
        // print last substring (if any)
        if ($l > 0) {
            switch ($align) {
                case 'J':
                case 'C': {
                        break;
                    }
                case 'L': {
                        if (!$this->rtl) {
                            $w = $l;
                        }
                        break;
                    }
                case 'R': {
                        if ($this->rtl) {
                            $w = $l;
                        }
                        break;
                    }
                default: {
                        $w = $l;
                        break;
                    }
            }
            $tmpstr = TCPDF_FONTS::UniArrSubString($uchars, $j, $nb);
            if ($firstline) {
                $startx = $this->x;
                $tmparr = array_slice($chars, $j, ($nb - $j));
                if ($rtlmode) {
                    $tmparr = TCPDF_FONTS::utf8Bidi($tmparr, $tmpstr, $this->tmprtl, $this->isunicode, $this->CurrentFont);
                }
                $linew = $this->GetArrStringWidth($tmparr);
                unset($tmparr);
                if ($this->rtl) {
                    $this->endlinex = $startx - $linew;
                } else {
                    $this->endlinex = $startx + $linew;
                }
                $w = $linew;
                $tmpcellpadding = $this->cell_padding;
                if ($maxh == 0) {
                    $this->setCellPadding(0);
                }
            }
            if ($firstblock and $this->isRTLTextDir()) {
                $tmpstr = $this->stringRightTrim($tmpstr);
            }
            $this->Cell($w, $h, $tmpstr, 0, $ln, $align, $fill, $link, $stretch);
            unset($tmpstr);
            if ($firstline) {
                $this->cell_padding = $tmpcellpadding;
                return (TCPDF_FONTS::UniArrSubString($uchars, $nb));
            }
            ++$nl;
        }
        if ($firstline) {
            return '';
        }
        return $nl;
    }
}
