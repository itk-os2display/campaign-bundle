<?php
/**
 * @file
 * This file is a part of the Os2DisplayCoreBundle.
 *
 * Contains the middleware communication service.
 */

namespace Itk\CampaignBundle\Service;

use Os2Display\CoreBundle\Events\CronEvent;
use JMS\Serializer\SerializationContext;
use Os2Display\CoreBundle\Entity\Channel;
use Os2Display\CoreBundle\Entity\SharedChannel;
use Symfony\Component\DependencyInjection\Container;
use Os2Display\CoreBundle\Services\TemplateService;
use Os2Display\CoreBundle\Services\UtilityService;

use Os2Display\CoreBundle\Services\MiddlewareCommunication as BaseService;

/**
 * Class MiddlewareCommunication
 *
 * @package Os2Display\CoreBundle\Services
 */
class MiddlewareCommunication extends BaseService
{
    /**
     * Find Id's of the screen using a channel.
     *
     * @param Channel|SharedChannel $channel
     *   The Channel or SharedChannel to push.
     *
     * @return array
     *   Id's of the screens that uses the channel.
     */
    private function getScreenIdsOnChannel($channel)
    {
        // Get screen ids.
        $regions = $channel->getChannelScreenRegions();
        $screenIds = array();
        foreach ($regions as $region) {
            if (!in_array($region->getScreen()->getId(), $screenIds)) {
                $screenIds[] = $region->getScreen()->getId();
            }
        }

        return $screenIds;
    }

    /**
     * Push a Channel or a SharedChannel to the middleware.
     *
     * @param Channel|SharedChannel $channel
     *   The Channel or SharedChannel to push.
     * @param mixed $data
     *   The Data that should be pushed for $channel encoded as json.
     * @param string $id
     *   The id of the channel (internal id for Channel, unique_id for SharedChannel)
     * @param boolean $force
     *   Should the push be forced through?
     */
    public function pushChannel($channel, $data, $id, $force)
    {
        $doctrine = $this->container->get('doctrine');
        $em = $doctrine->getManager();

        // Calculate hash of content, used to avoid unnecessary push.
        $sha1 = sha1($data);

        $middlewarePath = $this->container->getParameter('middleware_host').
            $this->container->getParameter('middleware_path');

        // Check if the channel should be pushed.
        if ($force || $sha1 !== $channel->getLastPushHash()) {
            // Get screen ids.
            $screenIds = $this->getScreenIdsOnChannel($channel);

            // Only push channel if it's attached to a least one screen. If no screen
            // is attached then channel will be deleted from the middleware and
            // $lastPushTime will be reset later on in this function.
            if (count($screenIds) > 0) {
                $curlResult = $this->utilityService->curl(
                    $middlewarePath.'/channel/'.$id,
                    'POST',
                    $data,
                    'middleware'
                );

                // If the result was delivered, update the last hash.
                if ($curlResult['status'] === 200) {
                    $lastPushScreens = $channel->getLastPushScreens();

                    // Push deletes to the middleware if a channel has been on a screen previously,
                    // but now has been removed.
                    $updatedScreensFailed = false;

                    $lastPushScreensArray = array();
                    if (!empty($lastPushScreens)) {
                        $lastPushScreensArray = json_decode($lastPushScreens);
                    }

                    foreach ($lastPushScreensArray as $lastPushScreenId) {
                        if (!in_array($lastPushScreenId, $screenIds)) {
                            $curlResult = $this->utilityService->curl(
                                $middlewarePath.'/channel/'.$id.'/screen/'.$lastPushScreenId,
                                'DELETE',
                                json_encode(array()),
                                'middleware'
                            );

                            if ($curlResult['status'] !== 200) {
                                $updatedScreensFailed = true;
                            }
                        }
                    }

                    // If the delete process was successful, update last push information.
                    // else set values to NULL to ensure new push.
                    if (!$updatedScreensFailed) {
                        $channel->setLastPushScreens(json_encode($screenIds));
                        $channel->setLastPushHash($sha1);
                    } else {
                        // Removing channel from some screens have failed, hence mark the
                        // channel for re-push.
                        $channel->setLastPushHash(null);
                    }
                } else {
                    // Channel push failed for this channel mark it for re-push.
                    $channel->setLastPushHash(null);
                }
            } else {
                // Channel don't have any screens, so delete from the middleware. This
                // will automatically remove it from any screen connected to the
                // middleware that displays is currently.
                $curlResult = $this->utilityService->curl(
                    $middlewarePath.'/channel/'.$id,
                    'DELETE',
                    json_encode(array()),
                    'middleware'
                );

                if ($curlResult['status'] !== 200) {
                    // Delete did't not work, so mark the channel for re-push.
                    $channel->setLastPushHash(null);
                } else {
                    // Channel delete push'ed, so set meta-data about screens to empty
                    // array, which is the current screen array.
                    $channel->setLastPushScreens(json_encode($screenIds));
                }
            }

            // Channel will have been update in all execution paths, so save the
            // channel to the database.
            $em->flush();
        }
    }

    /**
     * Pushes the channels for each screen to the middleware.
     *
     * @param boolean $force
     *   Should the push to screen be forced, even though the content has previously been pushed to the middleware?
     */
    public function pushToScreens($force = false)
    {
        // Get doctrine handle
        $doctrine = $this->container->get('doctrine');

        $serializer = $this->container->get('jms_serializer');

        // Push channels
        $channels = $doctrine->getRepository('Os2DisplayCoreBundle:Channel')->findAll();

        foreach ($channels as $channel) {
            $data = $serializer->serialize(
                $channel,
                'json',
                SerializationContext::create()
                    ->setGroups(array('middleware'))
            );

            $this->pushChannel($channel, $data, $channel->getId(), $force);
        }

        // Push shared channels
        $sharedChannels = $doctrine->getRepository(
            'Os2DisplayCoreBundle:SharedChannel'
        )
            ->findAll();

        foreach ($sharedChannels as $sharedChannel) {
            $data = $serializer->serialize(
                $sharedChannel,
                'json',
                SerializationContext::create()
                    ->setGroups(array('middleware'))
            );

            // Hack to get slides encoded correctly
            //   Issue with how the slides array is encoded in jms_serializer.
            $d = json_decode($data);
            $d->data->slides = json_decode($d->data->slides);
            $data = json_encode($d);

            if ($data === null) {
                continue;
            }

            $this->pushChannel(
                $sharedChannel,
                $data,
                $sharedChannel->getUniqueId(),
                $force
            );
        }
    }
}
