<?php

namespace App\Support\Dom;

use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use RuntimeException;

/**
 * Thin wrapper around PHP's native DOMDocument/DOMXPath (no external
 * dependency) for scrapers. Unlike regex against raw HTML, XPath selection
 * is immune to nested tags, attribute ordering, and incidental whitespace
 * changes in the markup being scraped.
 */
class HtmlDocument
{
    private DOMXPath $xpath;

    public function __construct(string $html)
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?>'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();

        $this->xpath = new DOMXPath($document);
    }

    public function first(string $expression, ?DOMElement $context = null): ?DOMElement
    {
        $node = $this->query($expression, $context)->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * @return DOMNodeList<DOMNameSpaceNode|DOMNode>
     */
    public function query(string $expression, ?DOMElement $context = null): DOMNodeList
    {
        $result = $context !== null
            ? $this->xpath->query($expression, $context)
            : $this->xpath->query($expression);

        if ($result === false) {
            throw new RuntimeException("Invalid XPath expression: {$expression}");
        }

        return $result;
    }

    /**
     * @return array<int, DOMElement>
     */
    public function elements(string $expression, ?DOMElement $context = null): array
    {
        $elements = [];

        foreach ($this->query($expression, $context) as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    public function text(?DOMElement $node): ?string
    {
        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);

        return $text !== '' ? $text : null;
    }

    public function attr(?DOMElement $node, string $name): ?string
    {
        if ($node === null || ! $node->hasAttribute($name)) {
            return null;
        }

        return $node->getAttribute($name);
    }

    /**
     * The trimmed text of each matching node's own text content, with the
     * common "&nbsp;"-only filler paragraph collapsed to an empty string so
     * callers can filter it out.
     *
     * @return array<int, string>
     */
    public function paragraphs(string $expression, ?DOMElement $context = null): array
    {
        $paragraphs = [];

        foreach ($this->query($expression, $context) as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }

            $paragraphs[] = trim(str_replace("\u{a0}", ' ', $node->textContent));
        }

        return $paragraphs;
    }

    /**
     * Whether a space-separated `class` attribute contains the given token,
     * regardless of the other classes present or their order.
     */
    public static function hasClass(string $token): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$token.' ")';
    }
}
