<?php

namespace MJ\Credits\BbCode;

/**
 * Class Charge
 *
 * @package MJ\Credits\BbCode
 */
class Charge
{
    /**
     * @param array $tagChildren
     * @param mixed $tagOption
     * @param array $tag
     * @param array $options
     * @param \XF\BbCode\Renderer\AbstractRenderer $renderer
     *
     * @return string
     * @throws \XF\PrintableException
     */
    public static function charge(
        array $tagChildren,
        $tagOption,
        array $tag,
        array $options,
        \XF\BbCode\Renderer\AbstractRenderer $renderer
    ): string {
        if (!isset($options['entity']))
        {
            // This must be outside the Show Thread page, ignore it
            return $renderer->renderSubTree($tag['children'], $options);
        }

        $tagOption = floatval($tagOption);

        /** @var \MJ\Credits\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if ($visitor->canBypassMJCreditsCharge() || $tagOption == 0.00)
        {
            // This user can bypass charge tags
            return $renderer->renderSubTree($tag['children'], $options);
        }

        [$userId, $contentId, $contentType] = self::getMetadataFromEntity($options['entity']);

        if (!$userId || !$contentId || !$contentType)
        {
            // Post must not be saved yet
            return $renderer->renderSubTree($tag['children'], $options);
        }

        // Get the container
        $bbCodeContainer = \XF::app()->bbCode();
        $rules = $bbCodeContainer->rules('base');

        // Render the content to prepare for hash checks
        // 	don't include renderer states as we want the rendering to be identical
        // 	to how it works in DiscussionMessage_Post
        $renderedContent = $bbCodeContainer->processor()->renderAst($tagChildren, $rules);
        $contentHash = md5($contentId . $renderedContent);

        /** @var \MJ\Credits\Entity\Charge $charge */
        $charge = \XF::finder('MJ\Credits:Charge')
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('content_hash', $contentHash)
            ->fetchOne();

        // Get the post info
        if (!$charge)
        {
            /** @var \MJ\Credits\Entity\Charge $charge */
            $charge = \XF::em()->create('MJ\Credits:Charge');
            $charge->content_type = $contentType;
            $charge->content_id = $contentId;
            $charge->content_hash = $contentHash;
            $charge->cost = $tagOption;
            $charge->save();
        }

        $button = self::renderButtonForUser($userId, $charge);
        if ($button !== null)
        {
            return $button;
        }

        // If users have paid for this
        return $renderer->renderSubTree($tag['children'], $options);
    }

    /**
     * @param int $userId
     * @param \MJ\Credits\Entity\Charge $charge
     *
     * @return string|null
     */
    protected static function renderButtonForUser(int $userId, \MJ\Credits\Entity\Charge $charge): ?string
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id)
        {
            return '
				<span>
					<input type="button" class="button" value="' . \XF::phrase('mjc_credits_costs_x_y', [
                    'param1' => $charge->Currency->getFormattedValue($charge->cost),
                    'param2' => $charge->Currency->title
                ]) . '" />
				</span>
			';
        }
        elseif (
            $userId
            && $userId != $visitor->user_id
            && !$charge->Purchases->offsetExists($visitor->user_id)
        ) {
            return '
				<span>
					<input
						type="button"
						class="button"
						data-xf-click="overlay"
						data-href="' . \XF::app()->router('public')->buildLink('mjc-credits/currency/buy-content', $charge->Currency, ['content_type' => $charge->content_type, 'content_id' => $charge->content_id, 'content_hash' => $charge->content_hash]) . '"
						value="' . \XF::phrase('mjc_credits_view_for_x_y', [
                    'param1' => $charge->Currency->getFormattedValue($charge->cost),
                    'param2' => $charge->Currency->title
                ]) . '"
					/>
				</span>
			';
        }

        return null;
    }

    /**
     * @param \XF\Mvc\Entity\Entity $entity
     * @return array
     */
    protected static function getMetadataFromEntity(\XF\Mvc\Entity\Entity $entity): array
    {
        if ($entity instanceof \XF\Entity\Post)
        {
            return [$entity->user_id, $entity->post_id, 'post'];
        }
        elseif ($entity instanceof \XFRM\Entity\ResourceUpdate)
        {
            return [$entity->Resource->user_id, $entity->resource_update_id, 'resource_update'];
        }
        elseif ($entity instanceof \XFMG\Entity\Album)
        {
            return [$entity->user_id, $entity->album_id, 'xfmg_album'];
        }
        elseif ($entity instanceof \XFMG\Entity\Comment)
        {
            return [$entity->user_id, $entity->comment_id, 'xfmg_comment'];
        }
        elseif ($entity instanceof \XFMG\Entity\MediaItem)
        {
            return [$entity->user_id, $entity->media_id, 'xfmg_media'];
        }
        else
        {
            return [0, 0, ''];
        }
    }
}