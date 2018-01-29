<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Helper;

use Mautic\CampaignBundle\Entity\ChannelInterface;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;

class ChannelExtractor
{
    /**
     * @param ChannelInterface      $entity
     * @param Event                 $event
     * @param AbstractEventAccessor $eventConfig
     */
    public static function setChannel(ChannelInterface $entity, Event $event, AbstractEventAccessor $eventConfig)
    {
        if ($entity->getChannel()) {
            return;
        }

        if (!$channel = $eventConfig->getChannel()) {
            return;
        }

        $entity->setChannel($channel);

        if (!$channelIdField = $eventConfig->getChannelIdField()) {
            return;
        }

        $properties = $event->getProperties();
        if (!empty($properties['properties'][$channelIdField])) {
            if (is_array($properties['properties'][$channelIdField])) {
                if (count($properties['properties'][$channelIdField]) === 1) {
                    // Only store channel ID if a single item was selected
                    $entity->setChannelId($properties['properties'][$channelIdField]);
                }

                return;
            }

            $entity->setChannelId($properties['properties'][$channelIdField]);
        }
    }
}
