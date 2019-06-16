<?php
declare(strict_types=1);

namespace Featdd\HtmlTermWrapper\Utility;

/***
 * The MIT License (MIT)
 *
 * Copyright (c) 2019 Daniel Dorndorf <dorndorf@featdd.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 ***/

use DOMDocument;
use DOMText;

/**
 * @package HtmlTermWrapper
 * @subpackage Utility
 */
class ParserUtility
{
    public const DEFAULT_TAG = 'HTMLTERMWRAPPER';

    /**
     * Protect inline JavaScript from DOM Manipulation with HTML comments
     * Optional you can pass over a alternative comment tag
     *
     * @param string $html
     * @param string $tag
     * @return string
     */
    public static function protectScrtiptsAndCommentsFromDOM(string $html, string $tag = self::DEFAULT_TAG): string
    {
        $callback = function (array $match) use ($tag) {
            return '<!--' . $tag . base64_encode($match[1] . $match[2] . $match[3]) . '-->';
        };

        return preg_replace_callback(
            '#(<script[^>]*>)(.*?)(<\/script>)#is',
            $callback,
            preg_replace_callback(
                '#(<!--\[[^<]*>|<!--)(.*?)(<!\[[^<]*>|-->)#s',
                $callback,
                $html
            )
        );
    }

    /**
     * Reverse inline JavaScript protection
     *
     * @param string $html
     * @param string $tag
     * @return string
     */
    public static function protectScriptsAndCommentsFromDOMReverse(string $html, string $tag = self::DEFAULT_TAG): string
    {
        $callback = function (array $match) {
            return base64_decode($match[2]);
        };

        return preg_replace_callback(
            '#(<!--' . preg_quote($tag, '#') . ')(.*?)(-->)#is',
            $callback,
            $html
        );
    }

    /**
     * Protect link and src attribute paths to be altered by dom
     *
     * @param string $html
     * @param string $tag
     * @return string
     */
    public static function protectLinkAndSrcPathsFromDOM(string $html, string $tag = self::DEFAULT_TAG): string
    {
        $callback = function (array $match) use ($tag) {
            return $match[1] . $match[2] . $tag . base64_encode($match[3]) . $match[4];
        };

        return preg_replace_callback(
            '#(href|src)(\=\")(.*?)(\")#is',
            $callback,
            $html
        );
    }

    /**
     * Reverse link and src paths protection
     *
     * @param string $html
     * @param string $tag
     * @return string
     */
    public static function protectLinkAndSrcPathsFromDOMReverse(string $html, string $tag = self::DEFAULT_TAG): string
    {
        $callback = function (array $match) {
            return $match[1] . $match[2] . base64_decode($match[4]) . $match[5];
        };

        return preg_replace_callback(
            '#(href|src)(\=\")(' . preg_quote($tag, '#') . ')(.*?)(\")#is',
            $callback,
            $html
        );
    }

    /**
     * Replaces a DOM Text node
     * with a replacement string
     *
     * @param \DOMText $DOMText
     * @param string $replacement
     * @return void
     */
    public static function domTextReplacer(DOMText $DOMText, string $replacement): void
    {
        if (false === empty(trim($replacement))) {
            $tempDOM = new DOMDocument();
            // use XHTML tag for avoiding UTF-8 encoding problems
            $tempDOM->loadHTML('<?xml encoding="UTF-8">' . '<!DOCTYPE html><html><body><div id="replacement">' . $replacement . '</div></body></html>');

            $replacementNode = $DOMText->ownerDocument->createDocumentFragment();

            /** @var \DOMElement $tempDOMChild */
            foreach ($tempDOM->getElementById('replacement')->childNodes as $tempDOMChild) {
                $tempChild = $DOMText->ownerDocument->importNode($tempDOMChild, true);
                $replacementNode->appendChild($tempChild);
            }

            $DOMText->parentNode->replaceChild($replacementNode, $DOMText);
        }
    }

    /**
     * @param string $html
     * @return string
     */
    public static function domHtml5Repairs(string $html): string
    {
        return preg_replace('/(<picture.*?>.*?)((<\/source>)+)(.*?<\/picture>)/is', '$1$4', $html);
    }
}
