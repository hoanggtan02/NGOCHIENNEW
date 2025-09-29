<?php

class etpl {
    private $data = [];
    private $loops = [];
    private $debug;
    private $xpathCache = [];
    private const APPEND = 'append';
    private const PREPEND = 'prepend';
    private const ADD = 'add';
    private const REMOVE = 'remove';
    private const TEXT = 'text';
    private const HTML = 'html';
    private const TEXT_CONTENT = 'textContent';
    private const ATTRIBUTES = 'attributes';
    private const STYLE = 'style';
    private const CLASS_ATTRIBUTE = 'class';
    private const ID_ATTRIBUTE = 'id';
    private $enableCache = false;
    private $cacheDir = 'cache/';
    private $cacheLifetime = 3600;
    public function __construct() {
        $this->debug = new stdClass();
        $this->debug->log = function ($type, $message) {
        };
    }
    private function cssToXpath($selector) {
        $selector = (string) $selector;
        if (isset($this->xpathCache[$selector])) {
            return $this->xpathCache[$selector];
        }
        $selector = str_replace(',', '|', $selector);
        $xpath = '//';
        if (preg_match('/^([a-zA-Z\*]+)$/', $selector, $matches)) {
            $xpath .= $matches[1];
        } elseif (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            $xpath .= '*[@id="' . $matches[1] . '"]';
        } elseif (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            $xpath .= '*[contains(concat(" ", @class, " "), " ' . $matches[1] . ' ")]';
        } else {
            $cssSelector = [
                '/\s*>\s*/',
                '/\s+\+\s+/',
                '/([a-zA-Z\*="\[\]#._-])\s+([a-zA-Z\*="\[\]#._-])/',
                '/([a-z#\.]\w*):first-child/',
                '/([a-z#\.]\w*):nth-child\((\d+)\)/',
                '/([a-z#\.]\w*):first/',
                '/([a-z#\.]\w*):nth\((\d+)\)/',
                '/([a-z]\w*)\[([a-z][\w\-_]*)\="([^"]*)"\]/',
                '/([a-z]\w*)\[([a-z][\w_\-]*)\]/',
                '/([a-z]\w*)\[!([a-z][\w\-_]*)\]/',
                '/\[([a-z][\w\-_]*)\=\"(.*)\"\]/',
                '/(?<=\])\[([a-z][\w\-_]*)\*\=\"([^"]+)\"\]/',
                '/\[([a-z][\w\-_]*)\*\=\"([^"]+)\"\]/',
                '/\[([a-z][\w_\-]*)\^\=\"([^"]+)\"\]/',
                '/\[([a-z][\w_\-]*)\$\=\"([^"]+)\"\]/',
                '/(?<=\])(?<! )\[([a-z][\w_\-]*)\]/',
                '/\[([a-z][\w_\-]*)\]/',
                '/(\w+)\[([a-z][\w\-]*)\*\]/',
                '/(?<=\])\[([a-z][\w\-]*)\*\]/',
                '/(?<!\])\[([a-z][\w\-]*)\*\]/',
                '/([a-z]\w*|\*)\.([a-z][\w\-_]*)\*/',
                '/([a-z]\w*|\*)\.([a-z][\w\-_]*)+/',
                '/\.([a-z][\w\-\_]*)\*/',
                '/\.([a-z][\w\-\_]*)+/',
                '/([a-z]\w*)\#([a-z][\w\-_]*)/',
                '/\#([a-z][\w\-_]*)/',
            ];
            $xpathQuery = [
                '/',
                '/following-sibling::*[1]/self::',
                '\1//\2',
                '*[1]/self::\1',
                '*[\2]/self::\1',
                '\1[1]',
                '\1[\2]',
                '\1[contains(@\2,"\3")]',
                '\1[@\2]',
                '\1[not(@\2)]',
                '*[contains(@\1,"\2")]',
                '[contains(@\1,"\2")]',
                '[contains(concat(" ", @\1, " "), "\2")]',
                '[starts-with(@\1,"\2")]',
                '[ends-with(@\1,"\2")]',
                '[@\1]',
                '*[@\1]',
                '\1[@*[starts-with(name(), "\2")]]',
                '[@*[starts-with(name(), "\1")]]',
                '*[@*[starts-with(name(), "\1")]]',
                '\1[contains(concat(" ", @class, " "), concat(" ", "\2"))]',
                '\1[contains(concat(" ", @class, " "), concat(" ", "\2", " "))]',
                '*[contains(concat(" ", @class, " "), concat(" ", "\1"))]',
                '*[contains(concat(" ", @class, " "), concat(" ", "\1", " "))]',
                '\1[@id="\2"]',
                '*[@id="\1"]',
            ];
            $xpath .= preg_replace($cssSelector, $xpathQuery, $selector);
        }
        return $this->xpathCache[$selector] = $xpath;
    }
    private function ensureSelectorExists(array &$data, string $selector): void {
        if (!isset($data[$selector])) {
            $data[$selector] = [];
        }
    }
    private function handleAppendPrepend(string $selector, string $command, $value): void {
        $parts = explode(':', $command);
        $action = $parts[0];
        $type = $parts[1];
        $this->ensureSelectorExists($this->data, $selector);
        $this->data[$selector][$action][] = ['type' => $type, 'value' => $value];
    }
    private function handleStyle(string $selector, string $command, $value): void {
        $styleName = substr($command, 6);
        $this->ensureSelectorExists($this->data, $selector);
        if (!isset($this->data[$selector][self::STYLE])) {
            $this->data[$selector][self::STYLE] = [];
        }
        $this->data[$selector][self::STYLE][$styleName] = $value;
    }
    private function handleAttribute(string $selector, string $command, $value, string $attributeName): void{
        $action = substr($command, strrpos($command, ':') + 1);
        $values = is_array($value) ? $value : preg_split('/\s+/', trim($value));
        $this->ensureSelectorExists($this->data, $selector);
        if (!isset($this->data[$selector][$attributeName])) {
            $this->data[$selector][$attributeName] = [self::ADD => [], self::REMOVE => []];
        }
        foreach ($values as $val) {
            if ($val !== '') {
                $this->data[$selector][$attributeName][$action][] = $val;
            }
        }
    }
    private function handleClass(string $selector, string $command, $value): void {
        $this->handleAttribute($selector, $command, $value, self::CLASS_ATTRIBUTE);
    }
    private function handleId(string $selector, string $command, $value): void {
        $this->handleAttribute($selector, $command, $value, self::ID_ATTRIBUTE);
    }
    public function set(string $selectorWithCommand, $value): void {
        if (strpos($selectorWithCommand, '|') !== false) {
            list($selector, $command) = explode('|', $selectorWithCommand, 2);
        } else {
            $selector = $selectorWithCommand;
            $command = self::TEXT_CONTENT;
        }
        if ($command === self::HTML) {
            $this->ensureSelectorExists($this->data, $selector);
            $this->data[$selector][self::HTML] = $value;
        } elseif (strpos($command, self::APPEND . ':') === 0 || strpos($command, self::PREPEND . ':') === 0) {
            $this->handleAppendPrepend($selector, $command, $value);
        } elseif (strpos($command, self::STYLE . ':') === 0) {
            $this->handleStyle($selector, $command, $value);
        } elseif (strpos($command, self::CLASS_ATTRIBUTE . ':') === 0) {
            $this->handleClass($selector, $command, $value);
        } elseif (strpos($command, self::ID_ATTRIBUTE . ':') === 0) {
            $this->handleId($selector, $command, $value);
        } elseif ($command === self::TEXT_CONTENT) {
            $this->ensureSelectorExists($this->data, $selector);
            $this->data[$selector][self::TEXT_CONTENT] = $value;
        } else {
            $this->ensureSelectorExists($this->data, $selector);
            if (!isset($this->data[$selector][self::ATTRIBUTES])) {
                $this->data[$selector][self::ATTRIBUTES] = [];
            }
            $this->data[$selector][self::ATTRIBUTES][$command] = $value;
        }
    }
    public function loop(string $selector, array $data): void {
        $this->loops[$selector] = $data;
    }
    public function enableCache(bool $enable = true) {
        $this->enableCache = $enable;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    public function setCacheLifetime(int $lifetime) {
        $this->cacheLifetime = $lifetime;
    }
    private function getCacheFile(string $templateFile): string {
        $baseName = basename($templateFile);
        return $this->cacheDir . md5($baseName) . '.cache';
    }
    public function render(string $templateFile): string {
        if ($this->enableCache) {
            $cacheFile = $this->getCacheFile($templateFile);
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheLifetime)) {
                return file_get_contents($cacheFile);
            }
        }
        if (!file_exists($templateFile)) {
            return "Lỗi: Không tìm thấy file template " . $templateFile;
        }
        $html = trim(file_get_contents($templateFile));
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        foreach ($this->data as $selector => $data) {
            $xpathSelector = $this->cssToXpath($selector);
            $elements = $xpath->query($xpathSelector);
            if (!empty($elements)) {
                foreach ($elements as $element) {
                    $this->applyElementData($dom, $element, $data);
                }
            }
        }
        foreach ($this->loops as $selector => $dataArray) {
            $xpathSelector = $this->cssToXpath($selector);
            $templateElements = $xpath->query($xpathSelector);
            if (!empty($templateElements)) {
                $templateElement = $templateElements->item(0);
                if ($templateElement->parentNode) {
                    $parentNode = $templateElement->parentNode;
                    foreach ($dataArray as $itemData) {
                        $clonedElement = $templateElement->cloneNode(true);
                        $this->applyDataToElement($clonedElement, $itemData, new DOMXPath($clonedElement->ownerDocument));
                        $parentNode->appendChild($clonedElement);
                    }
                    $parentNode->removeChild($templateElement);
                }
            }
        }
        $output = $dom->saveHTML();
        if ($this->enableCache) {
            file_put_contents($this->getCacheFile($templateFile), $output);
        }
        return $output;
    }

    private function applyStyles(DOMElement $element, array $stylesToAdd): void {
        $currentStyle = $element->getAttribute(self::STYLE);
        $styles = [];
        if ($currentStyle) {
            foreach (explode(';', $currentStyle) as $style) {
                if (trim($style) !== '') {
                    list($key, $value) = explode(':', $style, 2);
                    $styles[trim($key)] = trim($value);
                }
            }
        }
        foreach ($stylesToAdd as $styleName => $styleValue) {
            $styles[$styleName] = htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8');
        }
        $styleString = '';
        foreach ($styles as $key => $value) {
            $styleString .= $key . ':' . $value . ';';
        }
        $element->setAttribute(self::STYLE, trim($styleString));
    }
    private function applyClassAttribute(DOMElement $element, array $classData): void {
        $currentClasses = array_filter(explode(' ', $element->getAttribute(self::CLASS_ATTRIBUTE)));
        if (isset($classData[self::ADD])) {
            foreach ($classData[self::ADD] as $classToAdd) {
                if (!in_array($classToAdd, $currentClasses)) {
                    $currentClasses[] = $classToAdd;
                }
            }
        }
        if (isset($classData[self::REMOVE])) {
            $currentClasses = array_diff($currentClasses, $classData[self::REMOVE]);
        }
        $element->setAttribute(self::CLASS_ATTRIBUTE, implode(' ', $currentClasses));
    }
    private function applyIdAttribute(DOMElement $element, array $idData): void {
        $currentIds = array_filter(explode(' ', $element->getAttribute(self::ID_ATTRIBUTE)));
        if (isset($idData[self::ADD])) {
            foreach ($idData[self::ADD] as $idToAdd) {
                if (!in_array($idToAdd, $currentIds)) {
                    $currentIds[] = $idToAdd;
                }
            }
        }
        if (isset($idData[self::REMOVE])) {
            $currentIds = array_diff($currentIds, $idData[self::REMOVE]);
        }
        $element->setAttribute(self::ID_ATTRIBUTE, implode(' ', $currentIds));
    }
    private function applyElementData(DOMDocument $dom, DOMElement $element, array $data): void {
        if (isset($data[self::HTML])) {
            while ($element->firstChild) {
                $element->removeChild($element->firstChild);
            }
            $fragment = $dom->createDocumentFragment();
            if ($fragment->appendXML($data[self::HTML])) {
                 $element->appendChild($fragment);
            }
        }
        elseif (isset($data[self::TEXT_CONTENT])) {
            $element->textContent = htmlspecialchars($data[self::TEXT_CONTENT], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data[self::ATTRIBUTES]) && is_array($data[self::ATTRIBUTES])) {
            foreach ($data[self::ATTRIBUTES] as $attributeName => $attributeValue) {
                $element->setAttribute($attributeName, htmlspecialchars($attributeValue, ENT_QUOTES, 'UTF-8'));
            }
        }
        if (isset($data[self::STYLE]) && is_array($data[self::STYLE])) {
            $this->applyStyles($element, $data[self::STYLE]);
        }
        foreach ([self::APPEND, self::PREPEND] as $operation) {
            if (isset($data[$operation]) && is_array($data[$operation])) {
                $insertBefore = ($operation === self::PREPEND) ? $element->firstChild : null;
                foreach ($data[$operation] as $item) {
                    $value = $item['value'];
                    if ($item['type'] === self::TEXT) {
                        $newNode = $dom->createTextNode(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
                    } elseif ($item['type'] === self::HTML) {
                        $newNode = $dom->createDocumentFragment();
                        $newNode->appendXML($value);
                    }
                    if ($insertBefore) {
                        $element->insertBefore($newNode, $insertBefore);
                    } else {
                        $element->appendChild($newNode);
                    }
                }
            }
        }
        if (isset($data[self::CLASS_ATTRIBUTE]) && is_array($data[self::CLASS_ATTRIBUTE])) {
            $this->applyClassAttribute($element, $data[self::CLASS_ATTRIBUTE]);
        }
        if (isset($data[self::ID_ATTRIBUTE]) && is_array($data[self::ID_ATTRIBUTE])) {
            $this->applyIdAttribute($element, $data[self::ID_ATTRIBUTE]);
        }
    }
    private function applyDataToElement(DOMElement $element, array $data, DOMXPath $globalXpath): void {
        $localDom = new DOMDocument();
        $importedNode = $localDom->importNode($element, true);
        $localDom->appendChild($importedNode);
        $xpath = new DOMXPath($localDom);
        foreach ($data as $key => $value) {
            if (strpos($key, '|') !== false) {
                list($selector, $modifier) = explode('|', $key,2);
                $selector = $this->cssToXpath($selector);
                $nodes = $xpath->query($selector);

                foreach ($nodes as $node) {
                    if (!$node instanceof DOMElement) {
                        continue;
                    }
                    if ($modifier === self::HTML) {
                        while ($node->firstChild) {
                            $node->removeChild($node->firstChild);
                        }
                        $fragment = $node->ownerDocument->createDocumentFragment();
                        if ($fragment->appendXML($value)) {
                             $node->appendChild($fragment);
                        }
                    }
                    elseif (strpos($modifier, self::STYLE . ':') === 0) {
                        $styleProp = substr($modifier, 6);
                        $currentStyle = $node->getAttribute(self::STYLE);
                        $styles = [];
                        if ($currentStyle) {
                            foreach (explode(';', $currentStyle) as $part) {
                                if (strpos($part, ':') !== false) {
                                    list($k, $v) = explode(':', $part, 2);
                                    $styles[trim($k)] = trim($v);
                                }
                            }
                        }
                        $styles[$styleProp] = $value;
                        $styleString = '';
                        foreach ($styles as $k => $v) {
                            $styleString .= "$k: $v; ";
                        }
                        $node->setAttribute(self::STYLE, trim($styleString));
                    }
                    elseif (strpos($modifier, self::CLASS_ATTRIBUTE . ':') === 0) {
                        $action = substr($modifier, 6);
                        $classList = array_filter(explode(' ', $node->getAttribute(self::CLASS_ATTRIBUTE)));

                        if (strpos($action, self::ADD) === 0) {
                            if (!in_array($value, $classList)) {
                                $classList[] = $value;
                            }
                        } elseif (strpos($action, self::REMOVE) === 0) {
                            $classList = array_diff($classList, is_array($value) ? $value : [$value]);
                        }
                        $node->setAttribute(self::CLASS_ATTRIBUTE, trim(implode(' ', $classList)));
                    }
                    elseif (strpos($modifier, self::ID_ATTRIBUTE . ':') === 0) {
                        $action = substr($modifier, 3);
                        $idList = array_filter(explode(' ', $node->getAttribute(self::ID_ATTRIBUTE)));

                        if (strpos($action, self::ADD) === 0) {
                            if (!in_array($value, $idList)) {
                                $idList[] = $value;
                            }
                        } elseif (strpos($action, self::REMOVE) === 0) {
                            $idList = array_diff($idList, is_array($value) ? $value : [$value]);
                        }
                        $node->setAttribute(self::ID_ATTRIBUTE, trim(implode(' ', $idList)));
                    }
                    elseif (strpos($modifier, self::APPEND . ':') === 0 || strpos($modifier, self::PREPEND . ':') === 0) {
                        $parts = explode(':', $modifier);
                        $action = $parts[0];
                        $type = $parts[1];
                        if ($type === self::TEXT) {
                            $newNode = $node->ownerDocument->createTextNode($value);
                        } elseif ($type === self::HTML) {
                            $newNode = $node->ownerDocument->createDocumentFragment();
                            $newNode->appendXML($value);
                        }
                        if ($action === self::APPEND) {
                            $node->appendChild($newNode);
                        } elseif ($action === self::PREPEND) {
                            $node->insertBefore($newNode, $node->firstChild);
                        }
                    }
                    else {
                        $node->setAttribute($modifier, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
                    }
                }
            } else {
                $selector = $this->cssToXpath($key);
                $nodes = $xpath->query($selector);
                foreach ($nodes as $node) {
                    if ($node instanceof DOMElement) {
                        $node->textContent = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }
        foreach ($importedNode->childNodes as $child) {
            $element->appendChild($element->ownerDocument->importNode($child, true));
        }
    }
}
?>