<?php

namespace App\Support\Ui;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Pulls the search box out of a mobile filter block so it can sit above the table
 * while the other controls stay behind a Filters button.
 */
final class MobileFilterSearchSplit
{
    /**
     * @return array{search: string, rest: string}
     */
    public static function split(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['search' => '', 'rest' => ''];
        }

        $root = self::load($html);
        if (! $root instanceof DOMElement) {
            return ['search' => '', 'rest' => $html];
        }

        $moved = self::extractSearchNodes($root);
        if ($moved === []) {
            return ['search' => '', 'rest' => $html];
        }

        $dom = $root->ownerDocument;
        $searchRoot = $dom->createElement('div');
        foreach ($moved as $node) {
            $searchRoot->appendChild($node);
        }

        return [
            'search' => self::innerHtml($searchRoot),
            'rest' => self::innerHtml($root),
        ];
    }

    /**
     * Prepare toolbar HTML that is about to be placed in a mobile drawer.
     * Search stays outside the sheet. Desktop-only markup is left untouched.
     *
     * @return array{search: string, sheet: string, inline: string, showButton: bool}
     */
    public static function forDrawer(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['search' => '', 'sheet' => '', 'inline' => '', 'showButton' => false];
        }

        $root = self::load($html);
        if (! $root instanceof DOMElement) {
            return ['search' => '', 'sheet' => $html, 'inline' => '', 'showButton' => self::htmlHasFilters($html)];
        }

        $moved = self::extractSearchNodes($root);
        self::unwrapMobileDetails($root);
        self::removeDesktopToolbar($root);

        $dom = $root->ownerDocument;
        $searchRoot = $dom->createElement('div');
        foreach ($moved as $node) {
            $searchRoot->appendChild($node);
        }

        $rest = self::innerHtml($root);
        $showButton = self::elementHasFilters($root);

        return [
            'search' => self::innerHtml($searchRoot),
            'sheet' => $showButton ? $rest : '',
            'inline' => $showButton ? '' : $rest,
            'showButton' => $showButton,
        ];
    }

    private static function load(string $html): ?DOMElement
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="mobile-filter-split-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $found = $dom->getElementById('mobile-filter-split-root');
        if ($found instanceof DOMElement) {
            return $found;
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@id="mobile-filter-split-root"]');
        $fallback = $nodes !== false ? $nodes->item(0) : null;

        return $fallback instanceof DOMElement ? $fallback : null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function extractSearchNodes(DOMElement $root): array
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $inputs = $xpath->query('.//input', $root);
        if ($inputs === false) {
            return [];
        }

        $nodes = [];
        foreach ($inputs as $input) {
            if (! $input instanceof DOMElement || ! self::isSearchInput($input) || self::isInsideDesktopToolbar($input)) {
                continue;
            }

            $node = self::movableNode($input, $root);
            self::preserveFormAssociation($node);
            $nodes[spl_object_id($node)] = $node;
        }

        return array_values($nodes);
    }

    private static function removeDesktopToolbar(DOMElement $root): void
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $nodes = $xpath->query('.//*[@data-property-filter-form-desktop] | .//*[contains(@class, "property-filter-toolbar__static")]', $root);
        if ($nodes === false) {
            return;
        }

        $remove = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private static function unwrapMobileDetails(DOMElement $root): void
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $detailsList = $xpath->query('.//details[contains(@class, "property-filter-mobile-toggle")]', $root);
        if ($detailsList === false) {
            return;
        }

        $details = [];
        foreach ($detailsList as $node) {
            if ($node instanceof DOMElement) {
                $details[] = $node;
            }
        }

        foreach ($details as $detail) {
            $parent = $detail->parentNode;
            if (! $parent instanceof DOMNode) {
                continue;
            }

            $children = [];
            foreach ($detail->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'summary') {
                    continue;
                }
                $parent->insertBefore($child, $detail);
            }

            $parent->removeChild($detail);
        }
    }

    private static function isSearchInput(DOMElement $input): bool
    {
        $type = strtolower($input->getAttribute('type') ?: 'text');
        if (in_array($type, ['hidden', 'checkbox', 'radio', 'file', 'password', 'submit', 'button', 'date', 'month', 'number', 'email', 'datetime-local', 'time'], true)) {
            return false;
        }

        if ($type === 'search' || $input->getAttribute('data-filter-search') === '1') {
            return true;
        }

        $name = strtolower($input->getAttribute('name'));
        if (in_array($name, ['q', 'search', 'query'], true)) {
            return true;
        }

        return str_contains(strtolower($input->getAttribute('placeholder')), 'search');
    }

    private static function isInsideDesktopToolbar(DOMElement $input): bool
    {
        $node = $input;
        while ($node instanceof DOMElement) {
            if ($node->hasAttribute('data-property-filter-form-desktop')) {
                return true;
            }
            if (str_contains($node->getAttribute('class'), 'property-filter-toolbar__static')) {
                return true;
            }
            $parent = $node->parentNode;

            $node = $parent instanceof DOMElement ? $parent : null;
        }

        return false;
    }

    private static function movableNode(DOMElement $input, DOMElement $root): DOMElement
    {
        $node = $input;
        while ($node->parentNode instanceof DOMElement && $node->parentNode !== $root) {
            $parent = $node->parentNode;
            if (strtolower($parent->tagName) === 'form' || self::containsOtherFilters($parent, $input)) {
                break;
            }
            $node = $parent;
        }

        return $node;
    }

    private static function containsOtherFilters(DOMElement $parent, DOMElement $searchInput): bool
    {
        foreach ($parent->getElementsByTagName('select') as $select) {
            if ($select instanceof DOMElement) {
                return true;
            }
        }

        foreach ($parent->getElementsByTagName('input') as $input) {
            if (! $input instanceof DOMElement || $input->isSameNode($searchInput) || self::isSearchInput($input)) {
                continue;
            }
            $type = strtolower($input->getAttribute('type') ?: 'text');
            if (in_array($type, ['date', 'month', 'number', 'datetime-local', 'time', 'hidden'], true)) {
                if ($type !== 'hidden') {
                    return true;
                }
            }
        }

        return false;
    }

    private static function preserveFormAssociation(DOMElement $node): void
    {
        $form = $node->parentNode;
        while ($form !== null && ! ($form instanceof DOMElement && strtolower($form->tagName) === 'form')) {
            $form = $form->parentNode;
        }
        if (! $form instanceof DOMElement) {
            return;
        }

        if ($form->getAttribute('id') === '') {
            $form->setAttribute('id', 'mobile-filter-form-'.substr(sha1(uniqid('', true)), 0, 8));
        }

        $formId = $form->getAttribute('id');
        $targets = [];
        if (strtolower($node->tagName) === 'input' || strtolower($node->tagName) === 'select' || strtolower($node->tagName) === 'textarea') {
            $targets[] = $node;
        }
        foreach (['input', 'select', 'textarea'] as $tag) {
            foreach ($node->getElementsByTagName($tag) as $control) {
                if ($control instanceof DOMElement) {
                    $targets[] = $control;
                }
            }
        }

        foreach ($targets as $control) {
            if ($control->getAttribute('form') === '') {
                $control->setAttribute('form', $formId);
            }
        }
    }

    private static function elementHasFilters(DOMElement $root): bool
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $nodes = $xpath->query('.//select | .//input', $root);
        if ($nodes === false) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement || self::isInsideDesktopToolbar($node)) {
                continue;
            }
            if (strtolower($node->tagName) === 'select') {
                return true;
            }
            $type = strtolower($node->getAttribute('type') ?: 'text');
            if (in_array($type, ['date', 'month', 'number', 'datetime-local', 'time'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function htmlHasFilters(string $html): bool
    {
        return (bool) preg_match('/<select\b/i', $html)
            || (bool) preg_match('/<input\b[^>]*\btype=["\'](?:date|month|number|datetime-local|time)["\']/i', $html);
    }

    private static function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return trim($html);
    }
}
