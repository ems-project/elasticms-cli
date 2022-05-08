<?php

namespace App\ExpressionLanguage;

use EMS\CommonBundle\Common\Standard\Json;
use Ramsey\Uuid\Uuid;

class Functions
{
    public static function domToJsonMenu(string $html, string $tag, string $fieldName, string $typeName, ?string $labelField = null): string
    {
        if ('' === \preg_replace('!\s+!', ' ', $html)) {
            return '[]';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        if (true !== $document->loadHTML(\sprintf('<?xml encoding="utf-8" ?><body>%s</body>', $html))) {
            throw new \RuntimeException(\sprintf('Unexpected error while loading this html %s', $html));
        }

        $nodeList = $document->getElementsByTagName('body');
        if (1 !== $nodeList->count()) {
            throw new \RuntimeException('Unexpected number of body node');
        }
        $body = $nodeList->item(0);
        if (!$body instanceof \DOMNode) {
            throw new \RuntimeException('Unexpected XLIFF type');
        }
        $current = [];
        $output = [];
        $body = self::trimDivContainers($body);
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMNode && $tag === $child->nodeName) {
                self::addNodeToJsonMenu($document, $current, $output, $tag, $fieldName, $typeName, $labelField);
                $current = [$child];
            } else {
                $current[] = $child;
            }
        }
        self::addNodeToJsonMenu($document, $current, $output, $tag, $fieldName, $typeName, $labelField);

        return Json::encode($output);
    }

    /**
     * @param \DOMNode[] $current
     * @param mixed[]    $output
     */
    private static function addNodeToJsonMenu(\DOMDocument $document, array $current, array &$output, string $tag, string $fieldName, string $typeName, ?string $labelField): void
    {
        if (empty($current)) {
            return;
        }
        $label = '';
        $body = '';
        foreach ($current as $child) {
            if ($child instanceof \DOMNode && $tag === $child->nodeName) {
                $label = $child->textContent;
            } else {
                $body .= $document->saveHTML($child);
            }
        }

        $item = [
            'id' => Uuid::uuid4()->toString(),
            'label' => $label,
            'type' => $typeName,
            'object' => [
                'label' => $label,
                $fieldName => $body,
            ],
        ];
        if (null !== $labelField) {
            $item['object'][$labelField] = $label;
        }
        $output[] = $item;
    }

    private static function trimDivContainers(\DOMElement $body): \DOMElement
    {
        $list = [];
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && '' === \preg_replace('!\s+!', '', $child->textContent)) {
                continue;
            }
            $list[] = $child;
        }
        if (1 === \count($list) && $list[0] instanceof \DOMElement && 'div' === $list[0]->nodeName) {
            return self::trimDivContainers($list[0]);
        }

        return $body;
    }
}
