<?php

namespace App\Twig;

use App\Storage\LogoStorage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private LogoStorage $logos)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logo_url', $this->logos->url(...)),
        ];
    }
}
