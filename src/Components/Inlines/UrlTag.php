<?php

namespace Erusev\Parsedown\Components\Inlines;

use Erusev\Parsedown\AST\StateRenderable;
use Erusev\Parsedown\Components\Inline;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\Parsing\Excerpt;
use Erusev\Parsedown\State;

final class UrlTag implements Inline
{
    use WidthTrait, DefaultBeginPosition;

    /** @var string */
    private $url;

    /**
     * @param string $url
     * @param int $width
     */
    public function __construct($url, $width)
    {
        $this->url = $url;
        $this->width = $width;
    }

    /**
     * @param Excerpt $Excerpt
     * @param State $State
     * @return static|null
     */
    public static function build(Excerpt $Excerpt, State $State)
    {
        if (\strpos($Excerpt->text(), '>') !== false and \preg_match('/^<(\w++:\/{2}[^ >]++)>/i', $Excerpt->text(), $matches)) {
            return new self($matches[1], \strlen($matches[0]));
        }

        return null;
    }

    /**
     * @return Element
     */
    public function stateRenderable(Parsedown $_)
    {
        return new Element('a', ['href' => $this->url], [new Text($this->url)]);
    }
}