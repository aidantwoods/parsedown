<?php

class PlainTextParsedownExt extends Parsedown
{
    protected function element(array $Element)
    {
        $markup = '';

        if (isset($Element['text']))
        {
            if (isset($Element['handler']))
            {
                $markup .= $this->{$Element['handler']}($Element['text']);
            }
            else
            {
                $markup .= $Element['text'];
            }
        }
        elseif (isset($Element['attributes']['alt']))
        {
            $markup .= $Element['attributes']['alt'];
        }

        return $markup;
    }
}
