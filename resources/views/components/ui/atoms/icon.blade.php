@props(['name'])

@php
    $path = public_path("vendor/lucide/{$name}.svg");
    $svg = file_exists($path) ? file_get_contents($path) : '';

    if ($svg !== '') {
        $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg, 1);

        preg_match('/<svg[^>]*>/', $svg, $openTagMatch, PREG_OFFSET_CAPTURE);
        $openTag = $openTagMatch[0][0];
        $openTagStart = $openTagMatch[0][1];

        preg_match_all('/([\w:-]+)="([^"]*)"/', $openTag, $attrMatches, PREG_SET_ORDER);
        $defaultAttributes = [];
        foreach ($attrMatches as $attrMatch) {
            $defaultAttributes[$attrMatch[1]] = $attrMatch[2];
        }

        $mergedAttributes = (string) $attributes->merge($defaultAttributes);
        $svg = substr_replace($svg, '<svg '.$mergedAttributes.'>', $openTagStart, strlen($openTag));
    }
@endphp
{!! $svg !!}
