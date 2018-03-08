<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Executioner\Event;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\DecisionAccessor;
use Mautic\CampaignBundle\Executioner\Dispatcher\EventDispatcher;
use Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException;
use Mautic\CampaignBundle\Executioner\Exception\DecisionNotApplicableException;
use Mautic\CampaignBundle\Executioner\Logger\EventLogger;
use Mautic\CampaignBundle\Executioner\Result\EvaluatedContacts;
use Mautic\LeadBundle\Entity\Lead;

class Decision implements EventInterface
{
    const TYPE = 'decision';

    /**
     * @var EventLogger
     */
    private $eventLogger;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * Action constructor.
     *
     * @param EventLogger $eventLogger
     */
    public function __construct(EventLogger $eventLogger, EventDispatcher $dispatcher)
    {
        $this->eventLogger = $eventLogger;
        $this->dispatcher  = $dispatcher;
    }

    /**
     * @param AbstractEventAccessor $config
     * @param ArrayCollection       $logs
     *
     * @return EvaluatedContacts|mixed
     *
     * @throws CannotProcessEventException
     */
    public function executeLogs(AbstractEventAccessor $config, ArrayCollection $logs)
    {
        $evaluatedContacts = new EvaluatedContacts();

        /** @var LeadEventLog $log */
        foreach ($logs as $log) {
            if (Event::TYPE_DECISION !== $log->getEvent()->getEventType()) {
                throw new CannotProcessEventException('Event ID '.$log->getEvent()->getId().' is not a decision');
            }

            try {
                /* @var DecisionAccessor $config */
                $this->execute($config, $log);
                $evaluatedContacts->pass($log->getLead());
            } catch (DecisionNotApplicableException $exception) {
                $evaluatedContacts->fail($log->getLead());
            }
        }

        $this->dispatcher->dispatchDecisionResultsEvent($config, $logs, $evaluatedContacts);

        return $evaluatedContacts;
    }

    /**
     * @param DecisionAccessor $config
     * @param Event            $event
     * @param Lead             $contact
     * @param null             $passthrough
     * @param null             $channel
     * @param null             $channelId
     *
     * @throws CannotProcessEventException
     * @throws DecisionNotApplicableException
     */
    public function evaluateForContact(DecisionAccessor $config, Event $event, Lead $contact, $passthrough = null, $channel = null, $channelId = null)
    {
        if (Event::TYPE_DECISION !== $event->getEventType()) {
            throw new CannotProcessEventException('Cannot process event ID '.$event->getId().' as a decision.');
        }

        $log = $this->eventLogger->buildLogEntry($event, $contact);
        $log->setChannel($channel)
            ->setChannelId($channelId);

        $this->execute($config, $log, $passthrough);
        $this->eventLogger->persistLog($log);
    }

    /**
     * @param DecisionAccessor $config
     * @param LeadEventLog     $log
     * @param null             $passthrough
     *
     * @throws DecisionNotApplicableException
     */
    private function execute(DecisionAccessor $config, LeadEventLog $log, $passthrough = null)
    {
        $decisionEvent = $this->dispatcher->dispatchDecisionEvent($config, $log, $passthrough);

        if (!$decisionEvent->wasDecisionApplicable()) {
            throw new DecisionNotApplicableException('evaluation failed');
        }
    }
}