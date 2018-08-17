<?php

namespace Os2Display\CampaignBundle\Service;

use Os2Display\CoreBundle\Services\MiddlewareService as BaseService;
use Os2Display\CampaignBundle\Entity\Campaign;
use Os2Display\CoreBundle\Entity\Screen;

class MiddlewareService extends BaseService
{
    /**
     * Is the screen affected by a campaign?
     *
     * @param Screen $screen The screen.
     * @return array
     */
    public function getCampaignsForScreen(Screen $screen)
    {
        $now = new \DateTime();

        $queryBuilder = $this->entityManager
            ->createQueryBuilder();

        $campaigns = $queryBuilder->select('campaign')
            ->from(Campaign::class, 'campaign')
            ->where(
                ':now between campaign.scheduleFrom and campaign.scheduleTo'
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    ':screen member of campaign.screens',
                    ':groups member of campaign.screenGroups'
                )
            )
            ->setParameter('screen', $screen)
            ->setParameter('groups', $screen->getGroups())
            ->setParameter('now', $now)
            ->getQuery()->getResult();

        return $campaigns;
    }

    /**
     * Get the current screen array.
     *
     * @param $screenId
     * @return object
     */
    public function getCurrentScreenArray($screenId)
    {
        $cachedResult = $this->cache->fetch('os2display.campaign.screen.' . $screenId);

        if ($cachedResult != false) {
            return $cachedResult;
        }

        // Get regular results.
        $result = parent::getCurrentScreenArray($screenId);

        $screen = $this->entityManager->getRepository(Screen::class)
            ->findOneById($screenId);

        // Modify results with campaigns.
        $campaigns = $this->getCampaignsForScreen($screen);

        if (count($campaigns) > 0) {
            // Remove all channels from region 1.
            foreach ($result->channels as $key => $aChannel) {
                $regions = [];

                foreach ($aChannel->regions as $region) {
                    if ($region != 1) {
                        $regions[] = $region;
                    }
                }

                if (count($regions) == 0) {
                    unset($result->channels[$key]);
                }
            }

            // Add all campaign channels to region 1.
            foreach ($campaigns as $campaign) {
                $channels = $campaign->getChannels();

                foreach ($channels as $channel) {
                    $channelId = $channel->getId();
                    $data = $this->getChannelArray($channel);

                    if (!isset($result->channels[$channelId])) {
                        $result->channels[$channelId] = (object)[
                            'data' => $data->data,
                            'regions' => [1],
                        ];
                    } else {
                        $regions = $result->channels[$channelId]->regions;

                        if (!in_array(1, $regions)) {
                            $result->channels[$channelId]->regions[] = 1;
                        }
                    }

                    // Hash the the channel object to avoid unnecessary updates in the frontend.
                    $result->channels[$channelId]->hash = sha1(json_encode($result->channels[$channelId]));
                }
            }
        }

        $this->cache->save('os2display.campaign.screen.' . $screenId, $result, $this->cacheTTL);

        return $result;
    }
}
