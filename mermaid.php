<?php

namespace Plugins\Mermaid;

use \Typemill\Plugin;
use Typemill\Models\ApiCalls;

class Mermaid extends Plugin
{

    public static function getSubscribedEvents()
    {
        return array(
            'onTwigLoaded'         => 'onTwigLoaded',
            'onExportHtmlLoaded'   => 'onExportHtmlLoaded'
        );
    }

    public function onTwigLoaded()
    {
        $mermaidSettings = $this->getPluginSettings();

        $theme         = $mermaidSettings['theme']         ?? 'default';
        $securityLevel = $mermaidSettings['securityLevel'] ?? 'strict';
        $htmlLabels    = $mermaidSettings['htmlLabels']      ?? 'true';
        $fontFamily    = $mermaidSettings['fontFamily']      ?? '';

        if (!empty($mermaidSettings['mermaidVersion'])) {
            $this->addJS('//unpkg.com/mermaid@' . $mermaidSettings['mermaidVersion'] . '/dist/mermaid.min.js');
        } else {
            $this->addJS('/mermaid/public/mermaid.min.js');
        }

        /* initialize the script */
        $this->addInlineJS('
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll("code.language-mermaid").forEach(function(element) {
                    var tempDiv = document.createElement("div");
                    tempDiv.className = "mermaid";
                    tempDiv.align = "center";
                    tempDiv.textContent = element.textContent;
                    element.parentNode.parentNode.replaceChild(tempDiv, element.parentNode);
                });
            });
        ');

        $config = [
            'theme'        => $theme,
            'securityLevel'=> $securityLevel,
            'htmlLabels'   => $htmlLabels === 'true',
            'fontFamily'   => $fontFamily,
        ];

        $this->addInlineJS('mermaid.initialize(' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');');
    }

    /**
     * Generates export-safe HTML by replacing browser-rendered mermaid code
     * blocks with static <img> tags pointing to cached SVG assets.
     *
     * This is needed for PDF/EPUB/static exports where client-side JavaScript
     * rendering is not available.
     */
    public function onExportHtmlLoaded($event)
    {
        $mermaidSettings = $this->getPluginSettings();

        $renderEndpoint = isset($mermaidSettings['render_endpoint']) ? trim($mermaidSettings['render_endpoint']) : '';
        if ($renderEndpoint == '')
        {
            return;
        }

        $data = $event->getData();
        $html = is_array($data) ? $data['html'] : $data;

        $html = preg_replace_callback(
            '/<pre><code\b[^>]*?\blanguage-mermaid\b[^>]*?>(.*?)<\/code><\/pre>/ms',
            function ($matches) use ($renderEndpoint, $mermaidSettings) {
                $code = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
                return $this->renderStaticMermaid($code, $renderEndpoint, $mermaidSettings);
            },
            $html
        );

        if (is_array($data))
        {
            $data['html'] = $html;
            $event->setData($data);
        }
        else
        {
            $event->setData($html);
        }
    }

    /**
     * Renders a mermaid diagram to a static SVG asset and returns the HTML img tag.
     */
    private function renderStaticMermaid(string $code, string $renderEndpoint, array $mermaidSettings): string
    {
        $code = trim($code);
        if ($code == '')
        {
            return '';
        }

        $theme         = $mermaidSettings['theme']         ?? 'default';
        $securityLevel = $mermaidSettings['securityLevel'] ?? 'strict';
        $htmlLabels    = ($mermaidSettings['htmlLabels']  ?? 'true') === 'true';
        $fontFamily    = $mermaidSettings['fontFamily']    ?? '';

        $cacheKey = json_encode([
            'code'          => $code,
            'theme'         => $theme,
            'securityLevel' => $securityLevel,
            'htmlLabels'    => $htmlLabels,
            'fontFamily'    => $fontFamily,
            'endpoint'      => $renderEndpoint,
        ]);

        $url = $this->generateStaticAsset(
            $cacheKey,
            function ($key) use ($renderEndpoint, $theme, $securityLevel, $htmlLabels, $fontFamily) {
                $params = json_decode($key, true);
                $svg = $this->fetchMermaidAsset(
                    $params['code'],
                    $renderEndpoint,
                    $theme,
                    $securityLevel,
                    $htmlLabels,
                    $fontFamily
                );

                if ($svg === false)
                {
                    return '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="40"><text x="10" y="25">Mermaid render error</text></svg>';
                }

                return $svg;
            },
            'svg'
        );

        return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="Mermaid diagram" class="mermaid-static" style="display: block; margin: 1em auto; max-width: 100%;" />';
    }

    /**
     * Fetches a rendered SVG from a remote mermaid rendering endpoint.
     */
    private function fetchMermaidAsset(
        string $code,
        string $renderEndpoint,
        string $theme,
        string $securityLevel,
        bool $htmlLabels,
        string $fontFamily
    ): string|false {
        $api = new ApiCalls();
        $api->setTimeout(15);

        $result = $api->makePostCall($renderEndpoint, [
            'mermaid'       => $code,
            'theme'         => $theme,
            'securityLevel' => $securityLevel,
            'htmlLabels'    => $htmlLabels,
            'fontFamily'    => $fontFamily,
            'format'        => 'svg',
        ]);

        if ($result !== false)
        {
            return $result;
        }

        return false;
    }
}