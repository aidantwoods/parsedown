<?php
class ParsedownExtensionMathJaxLaTeX extends Parsedown{
    public function __construct()
    {
        $this->InlineTypes['`'][] = 'MathJaxLaTeX';
        $this->BlockTypes['`'][] = 'FencedMathJaxLaTeX';
    }

    protected function inlineCode($Excerpt)
    {
        $marker = $Excerpt['text'][0];

        if (preg_match('/^('.$marker.'{2,})[ ]*(.+?)[ ]*(?<!'.$marker.')\1(?!'.$marker.')/s', $Excerpt['text'], $matches))
        {
            $text = $matches[2];
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            $text = preg_replace("/[ ]*\n/", ' ', $text);

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'code',
                    'text' => $text,
                ),
            );
        }
    }

    protected function inlineMathJaxLaTeX($Excerpt)
    {
        $marker = $Excerpt['text'][0];

        if (preg_match('/^('.$marker.')[ ]*(.+?)[ ]*(?<!'.$marker.')\1(?!'.$marker.')/s', $Excerpt['text'], $matches))
        {
            $text = $matches[2];
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            $text = preg_replace("/[ ]*\n/", ' ', $text);

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'span',
                    'text' => '\('.$text.'\)',
                ),
            );
        }
    }

    #
    # Fenced Code

    protected function blockFencedCode($Line)
    {
        if (preg_match('/^['.$Line['text'][0].']{3,}[ ]*([\w-]+)?[ ]*$/', $Line['text'], $matches))
        {
            $Element = array(
                'name' => 'code',
                'text' => '',
            );

            if (isset($matches[1]))
            {
                if (strtolower($matches[1]) === 'latex')
                {
                    return;
                }

                $class = 'language-'.$matches[1];

                $Element['attributes'] = array(
                    'class' => $class,
                );
            }

            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => $Element,
                ),
            );

            return $Block;
        }
    }

    #
    # Fenced MathJax

    protected function blockFencedMathJaxLaTeX($Line)
    {
        if (preg_match('/^['.$Line['text'][0].']{3,}[ ]*([\w-]+)?[ ]*$/', $Line['text'], $matches))
        {
            if ( ! isset($matches[1]) or strtolower($matches[1]) !== 'latex')
            {
                return;
            }

            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'span',
                    'text' => '',
                ),
            );

            return $Block;
        }
    }

    protected function blockFencedMathJaxLaTeXContinue($Line, $Block)
    {
        if (isset($Block['complete']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            $Block['element']['text'] .= "\n";

            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,}[ ]*$/', $Line['text']))
        {
            $Block['element']['text'] = substr($Block['element']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['text'] .= "\n".$Line['body'];;

        return $Block;
    }

    protected function blockFencedMathJaxLaTeXComplete($Block)
    {
        $text = $Block['element']['text'];

        $Block['element']['text'] = "\$\$\n" . $text . "\n\$\$";

        return $Block;
    }

}
?>
