<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Executioner\ContactFinder;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\CampaignBundle\Executioner\Exception\NoContactsFound;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Psr\Log\LoggerInterface;

class KickoffContacts
{
    /**
     * @var LeadRepository
     */
    private $leadRepository;

    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * KickoffContacts constructor.
     *
     * @param LeadRepository     $leadRepository
     * @param CampaignRepository $campaignRepository
     * @param LoggerInterface    $logger
     */
    public function __construct(LeadRepository $leadRepository, CampaignRepository $campaignRepository, LoggerInterface $logger)
    {
        $this->leadRepository     = $leadRepository;
        $this->campaignRepository = $campaignRepository;
        $this->logger             = $logger;
    }

    /**
     * @param                $campaignId
     * @param ContactLimiter $limiter
     *
     * @return ArrayCollection
     *
     * @throws NoContactsFound
     */
    public function getContacts($campaignId, ContactLimiter $limiter)
    {
        // Get list of all campaign leads; start is always zero in practice because of $pendingOnly
        $campaignContacts = $this->campaignRepository->getPendingContactIds($campaignId, $limiter);

        if (empty($campaignContacts)) {
            // No new contacts found in the campaign

            throw new NoContactsFound();
        }

        $this->logger->debug('CAMPAIGN: Processing the following contacts: '.implode(', ', $campaignContacts));

        // Fetch entity objects for the found contacts
        $contacts = $this->leadRepository->getContactCollection($campaignContacts);

        if (!count($contacts)) {
            // Just a precaution in case non-existent contacts are lingering in the campaign leads table
            $this->logger->debug('CAMPAIGN: No contact entities found.');

            throw new NoContactsFound();
        }

        return $contacts;
    }

    /**
     * @param       $campaignId
     * @param array $eventIds
     * @param null  $specificContactId
     *
     * @return mixed
     */
    public function getContactCount($campaignId, array $eventIds, ContactLimiter $limiter)
    {
        return $this->campaignRepository->getPendingEventContactCount($campaignId, $eventIds, $limiter);
    }

    /**
     * Clear Lead entities from memory.
     */
    public function clear()
    {
        $this->leadRepository->clear();
    }
}
