<?php

namespace Erusev\Parsedown;

use Erusev\Parsedown\AST\StateRenderable;
use Erusev\Parsedown\Components\AcquisitioningBlock;
use Erusev\Parsedown\Components\Block;
use Erusev\Parsedown\Components\Blocks\Paragraph;
use Erusev\Parsedown\Components\ContinuableBlock;
use Erusev\Parsedown\Components\Inline;
use Erusev\Parsedown\Components\Inlines\PlainText;
use Erusev\Parsedown\Components\StateUpdatingBlock;
use Erusev\Parsedown\Configurables\BlockTypes;
use Erusev\Parsedown\Configurables\InlineTypes;
use Erusev\Parsedown\Html\Renderable;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Parsing\Excerpt;
use Erusev\Parsedown\Parsing\Line;
use Erusev\Parsedown\Parsing\Lines;

final class Parsedown
{
    const version = '2.0.0-dev';

    /** @var State */
    private $State;

    public function __construct(StateBearer $StateBearer = null)
    {
        $StateBearer = $StateBearer ?: new State;

        $this->State = $StateBearer->state();
    }

    /**
     * @param string $text
     * @return string
     */
    public function text($text)
    {
        list($StateRenderables, $State) = self::lines(
            Lines::fromTextLines($text, 0),
            $this->State
        );

        $Renderables = $State->applyTo($StateRenderables);

        $html = self::render($Renderables);

        # trim line breaks
        $html = \trim($html, "\n");

        return $html;
    }

    /**
     * @return array{0: StateRenderable[], 1: State}
     */
    public static function lines(Lines $Lines, State $State)
    {
        list($Blocks, $State) = self::blocks($Lines, $State);

        return [self::stateRenderablesFrom($Blocks), $State];
    }

    /**
     * @param Component[] $Components
     * @return StateRenderable[]
     */
    public static function stateRenderablesFrom($Components)
    {
        return \array_map(
            /**
             * @param Component $Component
             * @return StateRenderable
             */
            function ($Component) { return $Component->stateRenderable(); },
            $Components
        );
    }

    /**
     * @return array{0: Block[], 1: State}
     */
    public static function blocks(Lines $Lines, State $State)
    {
        /** @var Block[] */
        $Blocks = [];
        /** @var Block|null */
        $Block = null;
        /** @var Block|null */
        $CurrentBlock = null;

        foreach ($Lines->contexts() as $Context) {
            $Line = $Context->line();

            if (
                isset($CurrentBlock)
                && $CurrentBlock instanceof ContinuableBlock
                && ! $CurrentBlock instanceof Paragraph
            ) {
                $Block = $CurrentBlock->advance($Context, $State);

                if ($Block instanceof StateUpdatingBlock) {
                    $State = $Block->latestState();
                }

                if (isset($Block)) {
                    $CurrentBlock = $Block;

                    continue;
                }
            }

            $marker = \substr($Line->text(), 0, 1);

            $potentialBlockTypes = \array_merge(
                $State->get(BlockTypes::class)->unmarked(),
                $State->get(BlockTypes::class)->markedBy($marker)
            );

            foreach ($potentialBlockTypes as $blockType) {
                $Block = $blockType::build($Context, $State, $CurrentBlock);

                if (isset($Block)) {
                    if ($Block instanceof StateUpdatingBlock) {
                        $State = $Block->latestState();
                    }

                    if (isset($CurrentBlock)
                        && (
                            ! $Block instanceof AcquisitioningBlock
                            || ! $Block->acquiredPrevious()
                        )
                    ) {
                        $Blocks[] = $CurrentBlock;
                    }

                    $CurrentBlock = $Block;

                    continue 2;
                }
            }

            if (isset($CurrentBlock) && $CurrentBlock instanceof Paragraph) {
                $Block = $CurrentBlock->advance($Context, $State);
            }

            if (isset($Block)) {
                $CurrentBlock = $Block;
            } else {
                if (isset($CurrentBlock)) {
                    $Blocks[] = $CurrentBlock;
                }

                $CurrentBlock = Paragraph::build($Context, $State);
            }
        }

        if (isset($CurrentBlock)) {
            $Blocks[] = $CurrentBlock;
        }

        return [$Blocks, $State];
    }

    /**
     * @param string $text
     * @return StateRenderable[]
     */
    public static function line($text, State $State)
    {
        return self::stateRenderablesFrom(self::inlines($text, $State));
    }

    /**
     * @param string $text
     * @return Inline[]
     */
    public static function inlines($text, State $State)
    {
        # standardize line breaks
        $text = \str_replace(["\r\n", "\r"], "\n", $text);

        /** @var Inline[] */
        $Inlines = [];

        # $excerpt is based on the first occurrence of a marker

        $InlineTypes = $State->get(InlineTypes::class);
        $markerMask = $InlineTypes->markers();

        for (
            $Excerpt = (new Excerpt($text, 0))->pushingOffsetTo($markerMask);
            $Excerpt->text() !== '';
            $Excerpt = $Excerpt->pushingOffsetTo($markerMask)
        ) {
            $marker = \substr($Excerpt->text(), 0, 1);

            foreach ($InlineTypes->markedBy($marker) as $inlineType) {
                $Inline = $inlineType::build($Excerpt, $State);

                if (! isset($Inline)) {
                    continue;
                }

                $markerPosition = $Excerpt->offset();
                $startPosition = $Inline->modifyStartPositionTo();

                if (! isset($startPosition)) {
                    $startPosition = $markerPosition;
                }

                $endPosition = $startPosition + $Inline->width();

                if ($startPosition > $markerPosition
                    || $endPosition < $markerPosition
                    || $startPosition < 0
                ) {
                    continue;
                }

                $Inlines[] = Plaintext::build($Excerpt->choppingUpToOffset($startPosition));

                $Inlines[] = $Inline;

                /** @psalm-suppress LoopInvalidation */
                $Excerpt = $Excerpt->choppingFromOffset($endPosition);

                continue 2;
            }

            /** @psalm-suppress LoopInvalidation */
            $Excerpt = $Excerpt->addingToOffset(1);
        }

        $Inlines[] = Plaintext::build($Excerpt->choppingFromOffset(0));

        return $Inlines;
    }

    /**
     * @param Renderable[] $Renderables
     * @return string
     */
    public static function render(array $Renderables)
    {
        return \array_reduce(
            $Renderables,
            /**
             * @param string $html
             * @return string
             */
            function ($html, Renderable $Renderable) {
                $newHtml = $Renderable->getHtml();

                return $html . ($newHtml === '' ? '' : "\n") . $newHtml;
            },
            ''
        );
    }
}
