<?php

declare(strict_types=1);

namespace App\Support\HtmlSanitizer;

use DOMDocument;
use DOMElement;
use Symfony\Component\HtmlSanitizer\Parser\ParserInterface;

/**
 * HTML parser for {@see \Symfony\Component\HtmlSanitizer\HtmlSanitizer} when PHP's
 * {@see \Dom\HTMLDocument} is unavailable (PHP &lt; 8.4). Symfony 8 defaults to NativeParser,
 * which requires Dom\HTMLDocument and breaks Filament notifications / HTML sanitization.
 */
final class LegacyDomHtmlParser implements ParserInterface
{
    public function parse(string $html, string $context = 'body'): \Dom\Node|\DOMNode|null
    {
        $markup = sprintf(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><%s>%s</%s></html>',
            $context,
            $html,
            $context
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            if (! @$dom->loadHTML($markup)) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }

        $element = $dom->getElementsByTagName($context)->item(0);
        if (! $element instanceof DOMElement) {
            return null;
        }

        return $element->hasChildNodes() ? $element : null;
    }
}
