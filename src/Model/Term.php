<?php
declare(strict_types=1);

namespace Featdd\HtmlTermWrapper\Model;

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

/**
 * @package HtmlTermWrapper
 * @subpackage Model
 */
class Term implements TermInterface
{
    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var int
     */
    protected $maxReplacements = -1;

    /**
     * @var bool
     */
    protected $caseSensitive = false;

    /**
     * @param string $name
     * @param int $maxReplacements
     * @param bool $caseSensitive
     */
    public function __construct(string $name, int $maxReplacements = -1, bool $caseSensitive = false)
    {
        $this->name = $name;
        $this->maxReplacements = $maxReplacements;
        $this->caseSensitive = $caseSensitive;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getMaxReplacements(): int
    {
        return $this->maxReplacements;
    }

    /**
     * @param int $maxReplacements
     */
    public function setMaxReplacements(int $maxReplacements): void
    {
        $this->maxReplacements = $maxReplacements;
    }

    /**
     * @return bool
     */
    public function isCaseSensitive(): bool
    {
        return $this->caseSensitive;
    }

    /**
     * @param bool $caseSensitive
     */
    public function setCaseSensitive(bool $caseSensitive): void
    {
        $this->caseSensitive = $caseSensitive;
    }
}
