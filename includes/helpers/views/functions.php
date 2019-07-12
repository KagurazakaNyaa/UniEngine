<?php

//  $params (Object)
//      - elementName (String)
//      - contentHTML (String)
//      - attrs (Object | undefined)
//
function buildDOMElementHTML($params) {
    $elementName = $params['elementName'];
    $contentHTML = $params['contentHTML'];

    $attrs = [];

    if (empty($params['attrs'])) {
        $params['attrs'] = [];
    }

    foreach ($params['attrs'] as $attrKey => $attrValue) {
        if ($attrValue === null) {
            continue;
        }

        $attrs[] = "{$attrKey}=\"{$attrValue}\"";
    }

    $attrs = implode(' ', $attrs);

    return ("<{$elementName} {$attrs}>{$contentHTML}</{$elementName}>");
}

//  $params (Object)
//      - text (String)
//      - href (String)
//      - query (Object | undefined)
//      - attrs (Object | undefined)
//
function buildLinkHTML($params) {
    $queryParams = [];

    if (empty($params['query'])) {
        $params['query'] = [];
    }
    if (empty($params['attrs'])) {
        $params['attrs'] = [];
    }

    foreach ($params['query'] as $paramKey => $paramValue) {
        $queryParams[] = "{$paramKey}={$paramValue}";
    }

    $href = $params['href'];
    $queryParams = implode('&', $queryParams);

    if ($queryParams) {
        $href .= "?{$queryParams}";
    }

    $params['attrs']['href'] = $href;

    return buildDOMElementHTML([
        'elementName' => 'a',
        'contentHTML' => $params['text'],
        'attrs' => $params['attrs']
    ]);
}

?>
