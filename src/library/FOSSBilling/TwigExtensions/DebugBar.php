<?php declare(strict_types=1);
/**
 * Copyright 2023 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\TwigExtensions;

use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DebugBar extends AbstractExtension
{
    private JavascriptRenderer $debugbarRenderer;

    public function getFunctions(): array
    {
        return [
            new TwigFunction('DebugBar_renderHead', [$this, 'renderHead'], ['is_safe' => ['html']]),
            new TwigFunction('DebugBar_render', [$this, 'render'], ['is_safe' => ['html']])
        ];
    }

    public function __construct()
    {
        $debugbar = new StandardDebugBar();
        $this->debugbarRenderer = $debugbar->getJavascriptRenderer();
    }

    public function getName(): string
    {
        return 'DebugBar';
    }

    public function renderHead(): string
    {
        return $this->debugbarRenderer->renderHead();
    }

    public function render(): string
    {
        return $this->debugbarRenderer->render();
    }
}
