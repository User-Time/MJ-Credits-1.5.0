<?php

namespace MJ\Credits\XF\Str;

/**
 * Class Formatter
 *
 * @package MJ\Credits\XF\Str
 */
class Formatter extends XFCP_Formatter
{
    /**
     * @param $string
     * @param array $options
     *
     * @return string
     */
    public function stripBbCode($string, array $options = [])
    {
        return parent::stripBbCode($this->stripMJCCreditsChargeBbCode($string), $options);
    }

    /**
     * @param $string
     * @param int $maxLength
     * @param array $options
     *
     * @return mixed|null|string|string[]
     */
    public function snippetString($string, $maxLength = 0, array $options = [])
    {
        return parent::snippetString($this->stripMJCCreditsChargeBbCode($string), $maxLength, $options);
    }

    /**
     * @param $string
     *
     * @return null|string|string[]
     */
    protected function stripMJCCreditsChargeBbCode($string)
    {
        $bbCode = preg_quote(\XF::options()->mjc_credits_event_trigger_content_bbcode, '#');

        return preg_replace(
            '#\[(' . $bbCode . ')(?![a-z0-9_])[^\]]*\].*\[/\\1\]#siU',
            \XF::phrase('mjc_credits_stripped_content'),
            $string
        );
    }
}