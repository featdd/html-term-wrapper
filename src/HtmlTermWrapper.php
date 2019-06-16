<?php
declare(strict_types=1);

namespace Featdd\HtmlTermWrapper;

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

use function count;
use function in_array;
use Closure;
use DOMDocument;
use DOMText;
use DOMXPath;

/**
 * @package HtmlTermWrapper
 */
class HtmlTermWrapper
{
    public const REGEX_DELIMITER = '/';
    public const DEFAULT_PARSING_TAGS = ['p'];

    /**
     * @var string[]
     */
    public static $alwaysIgnoreParentTags = ['script'];

    /**
     * @var string[]
     */
    public static $forbiddenParentTags = [];

    /**
     * @var string[]
     */
    public static $forbiddenParsingTagClasses = [];

    /**
     * @var string[]
     */
    protected $parsingTags = self::DEFAULT_PARSING_TAGS;

    /**
     * @var \Featdd\HtmlTermWrapper\Model\TermInterface[]
     */
    protected $terms = [];

    /**
     * @var int[]
     */
    protected $replacementCounters = [];

    /**
     * @param \Featdd\HtmlTermWrapper\Model\TermInterface[] $terms
     */
    public function __construct(Model\TermInterface... $terms)
    {
        $this->setTerms(...$terms);
    }

    /**
     * @param \Featdd\HtmlTermWrapper\Model\TermInterface[] $terms
     */
    public function setTerms(Model\TermInterface... $terms)
    {
        $this->terms = $terms;

        /** @var \Featdd\HtmlTermWrapper\Model\TermInterface $term */
        foreach ($this->terms as $key => $term) {
            $this->replacementCounters[$key] = $term->getMaxReplacements();
        }
    }

    /**
     * @param string[] $parsingTags
     */
    public function setParsingTags(array $parsingTags = self::DEFAULT_PARSING_TAGS)
    {
        $this->parsingTags = array_diff($parsingTags, self::$alwaysIgnoreParentTags);
    }

    /**
     * @param string $html
     * @param \Closure $wrapperClosure
     * @param bool $resetReplaceCountersAfterParsing
     * @return string
     * @throws \Featdd\HtmlTermWrapper\Exception\ParserException
     */
    public function parseHtml(string $html, Closure $wrapperClosure, bool $resetReplaceCountersAfterParsing = true): string
    {
        // Abort parser...
        if (
            // no tags to parse given
            0 === count($this->parsingTags) ||
            // no terms have been found
            0 === count($this->terms)
        ) {
            return $html;
        }

        // Add "a" if unknowingly deleted to prevent errors
        if (false === in_array(self::$alwaysIgnoreParentTags, static::$forbiddenParentTags, true)) {
            $forbiddenParentTags = array_unique(
                array_merge(static::$forbiddenParentTags, self::$alwaysIgnoreParentTags)
            );
        } else {
            $forbiddenParentTags = static::$forbiddenParentTags;
        }

        $removeHtmlAndBodyTag = false;

        if (0 === preg_match('/<html.*?>.*<body.*?>/is', $html)) {
            $html = '<html><body>' . $html . '</body></html>';
            $removeHtmlAndBodyTag = true;
        }

        //Create new DOMDocument
        $DOM = new DOMDocument();

        // Prevent crashes caused by HTML5 entities with internal errors
        libxml_use_internal_errors(true);

        // Load Page HTML in DOM and check if HTML is valid else abort
        // use XHTML tag for avoiding UTF-8 encoding problems
        if (
            false === $DOM->loadHTML(
                '<?xml encoding="UTF-8">' . Utility\ParserUtility::protectLinkAndSrcPathsFromDOM(
                    Utility\ParserUtility::protectScrtiptsAndCommentsFromDOM(
                        $html
                    )
                )
            )
        ) {
            throw new Exception\ParserException('Parsers DOM Document could\'nt load the html');
        }

        // remove unnecessary whitespaces in nodes (no visible whitespace)
        $DOM->preserveWhiteSpace = false;

        // Init DOMXPath with main DOMDocument
        $DOMXPath = new DOMXPath($DOM);

        /** @var \DOMNode $DOMBody */
        $DOMBody = $DOM->getElementsByTagName('body')->item(0);

        // iterate over tags which are defined to be parsed
        foreach ($this->parsingTags as $tag) {
            $xpathQuery = '//' . $tag;

            // if classes given add them to xpath query
            if (0 < count(static::$forbiddenParsingTagClasses)) {
                $xpathQuery .= '[not(contains(@class, \'' .
                    implode(
                        '\') or contains(@class, \'',
                        static::$forbiddenParsingTagClasses
                    ) .
                    '\'))]';
            }

            // extract the tags
            $DOMTags = $DOMXPath->query($xpathQuery, $DOMBody);
            // call the nodereplacer for each node to parse its content
            /** @var \DOMNode $DOMTag */
            foreach ($DOMTags as $DOMTag) {
                // get parent tags from root tree string
                $parentTags = explode(
                    '/',
                    preg_replace(
                        '#\[([^\]]*)\]#',
                        '',
                        substr($DOMTag->parentNode->getNodePath(), 1)
                    )
                );

                // check if element is children of a forbidden parent
                if (false === in_array($parentTags, $forbiddenParentTags, true)) {
                    /** @var \DOMNode $childNode */
                    for ($i = 0; $i < $DOMTag->childNodes->length; $i++) {
                        $childNode = $DOMTag->childNodes->item($i);

                        if ($childNode instanceof DOMText) {
                            Utility\ParserUtility::domTextReplacer(
                                $childNode,
                                $this->textParser(
                                    $childNode->ownerDocument->saveHTML($childNode),
                                    $wrapperClosure
                                )
                            );
                        }
                    }
                }
            }
        }

        if (true === $resetReplaceCountersAfterParsing) {
            $this->setTerms(...$this->terms);
        }

        // return the parsed html page and remove XHTML tag which is not needed anymore
        $html = str_replace(
            '<?xml encoding="UTF-8">',
            '',
            Utility\ParserUtility::protectScriptsAndCommentsFromDOMReverse(
                Utility\ParserUtility::protectLinkAndSrcPathsFromDOMReverse(
                    Utility\ParserUtility::domHtml5Repairs(
                        $DOM->saveHTML()
                    )
                )
            )
        );

        if (true === $removeHtmlAndBodyTag) {
            $html = preg_replace('/.*<body.*?>(.*)<\/body>.*/si', '$1', $html);
        }

        return $html;
    }

    /**
     * Parse the extracted html for terms
     *
     * @param string $text the text to be parsed
     * @param \Closure $wrapperClosure the wrapping function for parsed terms as callback
     * @return string
     */
    protected function textParser(string $text, Closure $wrapperClosure): string
    {
        $text = preg_replace('#\x{00a0}#iu', '&nbsp;', $text);
        /** @var \Featdd\HtmlTermWrapper\Model\TermInterface $term */
        foreach ($this->terms as $key => $term) {
            $replacementCounter = &$this->replacementCounters[$key];

            //Check replacement counter
            if (0 !== $replacementCounter) {
                $this->regexParser($text, clone $term, $replacementCounter, $wrapperClosure);
            }
        }

        return $text;
    }

    /**
     * Regex parser for terms on a text string
     *
     * @param string $text
     * @param \Featdd\HtmlTermWrapper\Model\TermInterface $term
     * @param int $replacementCounter
     * @param \Closure $wrapperClosure
     */
    protected function regexParser(string &$text, Model\TermInterface $term, int &$replacementCounter, Closure $wrapperClosure): void
    {
        // Try simple search first to save performance
        if (false === mb_stripos($text, $term->getName())) {
            return;
        }

        /*
         * Regex Explanation:
         * Group 1: (^|[\s\>[:punct:]]|\<br*\>)
         *  ^         = can be begin of the string
         *  \G        = can match an other matchs end
         *  \s        = can have space before term
         *  \>        = can have a > before term (end of some tag)
         *  [:punct:] = can have punctuation characters like .,?!& etc. before term
         *  \<br*\>   = can have a "br" tag before
         *
         * Group 2: (' . preg_quote($term->getName()) . ')
         *  The term to find, preg_quote() escapes special chars
         *
         * Group 3: ($|[\s\<[:punct:]]|\<br*\>)
         *  Same as Group 1 but with end of string and < (start of some tag)
         *
         * Group 4: (?![^<]*>|[^<>]*<\/)
         *  This Group protects any children element of the tag which should be parsed
         *  ?!        = negative lookahead
         *  [^<]*>    = match is between < & > and some other character
         *              avoids parsing terms in self closing tags
         *              example: <TERM> will work <TERM > not
         *  [^<>]*<\/ = match is between some tag and tag ending
         *              example: < or >TERM</>
         *
         * Flags:
         * i = ignores camel case
         */
        $regex = self::REGEX_DELIMITER .
            '(^|\G|[\s\>[:punct:]]|\<br*\>)' .
            '(' . preg_quote($term->getName(), self::REGEX_DELIMITER) . ')' .
            '($|[\s\<[:punct:]]|\<br*\>)' .
            '(?![^<]*>|[^<>]*<\/)' .
            self::REGEX_DELIMITER .
            (false === $term->isCaseSensitive() ? 'i' : '');

        // replace callback
        $callback = function (array $match) use ($term, &$replacementCounter, $wrapperClosure) {
            //decrease replacement counter
            if (0 < $replacementCounter) {
                $replacementCounter--;
            }

            // Use term match to keep original camel case
            $term->setName($match[2]);

            // Wrap replacement with original chars
            return $match[1] . $wrapperClosure($term) . $match[3];
        };

        // Use callback to keep allowed chars around the term and his camel case
        $text = (string) preg_replace_callback($regex, $callback, $text, $replacementCounter);
    }
}
