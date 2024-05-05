<?php

namespace Plugins\Mermaid;

use \Typemill\Plugin;

class Mermaid extends Plugin
{

    public static function getSubscribedEvents()
    {
        return array(
            'onTwigLoaded' => 'onTwigLoaded'
        );
    }

    public function onTwigLoaded()
    {
        $mermaidSettings = $this->getPluginSettings();

        if (isset($mermaidSettings['theme'])) {
            $theme = $mermaidSettings['theme'];
        } else {
            $theme = 'default';
        }

        if (isset($mermaidSettings['securityLevel'])) {
            $securityLevel = $mermaidSettings['securityLevel'];
        } else {
            $securityLevel = 'strict';
        }

        if (isset($mermaidSettings['htmlLabels'])) {
            $htmlLabels = $mermaidSettings['htmlLabels'];
        } else {
            $htmlLabels = 'true';
        }

        if (isset($mermaidSettings['fontFamily'])) {
            $fontFamily = $mermaidSettings['fontFamily'];
        } else {
            $fontFamily = '';
        }

        if (isset($mermaidSettings['mermaidVersion']) && !empty($mermaidSettings['mermaidVersion'])) {
            $this->addJS('//unpkg.com/mermaid@' . $mermaidSettings['mermaidVersion'] . '/dist/mermaid.min.js');
        } else {
            $this->addJS('/mermaid/public/mermaid.min.js');
        }

        /* initialize the script */
        $this->addInlineJS('
			document.addEventListener("DOMContentLoaded", function() {
				document.querySelectorAll("code.language-mermaid").forEach(function(element, index) {
					var content = element.innerHTML.replace(/&amp;/g, "&");
					tempDiv = document.createElement("div");
					tempDiv.className = "mermaid";
					tempDiv.align = "center";
					tempDiv.innerHTML = content;
					element.parentNode.parentNode.replaceChild(tempDiv, element.parentNode);
				});
			});
		');

        $this->addInlineJS("mermaid.initialize({'theme': '" . $theme . "', 'securityLevel': '" . $securityLevel . "', 'htmlLabels': " . $htmlLabels . ", 'fontFamily': '" . $fontFamily . "'});");
    }
}