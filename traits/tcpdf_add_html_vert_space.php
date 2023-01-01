<?php

trait TCPDF_ADD_HTML_VERT_SPACE
{
    /**
     * Add vertical spaces if needed.
     * @param string $hbz Distance between current y and line bottom.
     * @param string $hb The height of the break.
     * @param boolean $cell if true add the default left (or right if RTL) padding to each new line (default false).
     * @param boolean $firsttag set to true when the tag is the first.
     * @param boolean $lasttag set to true when the tag is the last.
     * @protected
     */
    protected function addHTMLVertSpace($hbz = 0, $hb = 0, $cell = false, $firsttag = false, $lasttag = false)
    {
        if ($firsttag) {
            $this->Ln(0, $cell);
            $this->htmlvspace = 0;
            return;
        }
        if ($lasttag) {
            $this->Ln($hbz, $cell);
            $this->htmlvspace = 0;
            return;
        }
        if ($hb < $this->htmlvspace) {
            $hd = 0;
        } else {
            $hd = $hb - $this->htmlvspace;
            $this->htmlvspace = $hb;
        }
        $this->Ln(($hbz + $hd), $cell);
    }
}
