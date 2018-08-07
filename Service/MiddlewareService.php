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
                ':screen member of campaign.screens'
            )
            ->setParameter('screen', $screen)
            ->setParameter('now', $now)
            ->getQuery()->getResult();

        return $campaigns;
    }

    public function getCurrentScreenArray($screenId) {
        $screen = $this->entityManager->getRepository(Screen::class)->findOneById($screenId);

        $campaigns = $this->getCampaignsForScreen($screen);

        // If no campaigns apply, run normal code.
        if (count($campaigns) === 0) {
            return parent::getCurrentScreenArray($screenId);
        }


    }
}
