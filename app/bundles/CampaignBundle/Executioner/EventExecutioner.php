<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Executioner;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\CampaignBundle\EventCollector\Accessor\Exception\TypeNotFoundException;
use Mautic\CampaignBundle\EventCollector\EventCollector;
use Mautic\CampaignBundle\Executioner\Event\Action;
use Mautic\CampaignBundle\Executioner\Event\Condition;
use Mautic\CampaignBundle\Executioner\Event\Decision;
use Mautic\CampaignBundle\Executioner\Logger\EventLogger;
use Mautic\CampaignBundle\Executioner\Result\Counter;
use Mautic\CampaignBundle\Executioner\Result\EvaluatedContacts;
use Mautic\CampaignBundle\Executioner\Result\Responses;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use Mautic\LeadBundle\Entity\Lead;
use Psr\Log\LoggerInterface;

class EventExecutioner
{
    /**
     * @var Action
     */
    private $actionExecutioner;

    /**
     * @var Condition
     */
    private $conditionExecutioner;

    /**
     * @var Decision
     */
    private $decisionExecutioner;

    /**
     * @var EventCollector
     */
    private $collector;

    /**
     * @var EventLogger
     */
    private $eventLogger;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventScheduler
     */
    private $scheduler;

    /**
     * @var \DateTime
     */
    private $now;

    /**
     * @var Responses
     */
    private $responses;

    /**
     * EventExecutioner constructor.
     *
     * @param EventCollector  $eventCollector
     * @param EventLogger     $eventLogger
     * @param Action          $actionExecutioner
     * @param Condition       $conditionExecutioner
     * @param Decision        $decisionExecutioner
     * @param LoggerInterface $logger
     * @param EventScheduler  $scheduler
     */
    public function __construct(
        EventCollector $eventCollector,
        EventLogger $eventLogger,
        Action $actionExecutioner,
        Condition $conditionExecutioner,
        Decision $decisionExecutioner,
        LoggerInterface $logger,
        EventScheduler $scheduler
    ) {
        $this->actionExecutioner    = $actionExecutioner;
        $this->conditionExecutioner = $conditionExecutioner;
        $this->decisionExecutioner  = $decisionExecutioner;
        $this->collector            = $eventCollector;
        $this->eventLogger          = $eventLogger;
        $this->logger               = $logger;
        $this->scheduler            = $scheduler;
        $this->now                  = new \DateTime();
    }

    /**
     * @param \DateTime $now
     */
    public function setNow(\DateTime $now)
    {
        $this->now = $now;
    }

    /**
     * @param Event          $event
     * @param Lead           $contact
     * @param Responses|null $responses
     * @param Counter|null   $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeForContact(Event $event, Lead $contact, Responses $responses = null, Counter $counter = null)
    {
        $this->responses = $responses;

        $contacts = new ArrayCollection([$contact->getId() => $contact]);

        $this->executeForContacts($event, $contacts, $counter);
    }

    /**
     * @param Event           $event
     * @param ArrayCollection $contacts
     * @param Counter|null    $counter
     * @param bool            $validatingInaction
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeForContacts(Event $event, ArrayCollection $contacts, Counter $counter = null, $isInactiveEvent = false)
    {
        if (!$contacts->count()) {
            $this->logger->debug('CAMPAIGN: No contacts to process for event ID '.$event->getId());

            return;
        }

        $config = $this->collector->getEventConfig($event);
        $logs   = $this->eventLogger->generateLogsFromContacts($event, $config, $contacts, $isInactiveEvent);

        $this->executeLogs($event, $logs, $counter);
    }

    /**
     * @param Event           $event
     * @param ArrayCollection $logs
     * @param Counter|null    $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeLogs(Event $event, ArrayCollection $logs, Counter $counter = null)
    {
        $this->logger->debug('CAMPAIGN: Executing '.$event->getType().' ID '.$event->getId());

        if (!$logs->count()) {
            $this->logger->debug('CAMPAIGN: No logs to process for event ID '.$event->getId());

            return;
        }

        $config = $this->collector->getEventConfig($event);

        if ($counter) {
            $counter->advanceExecuted($logs->count());
        }

        switch ($event->getEventType()) {
            case Event::TYPE_ACTION:
                $this->executeAction($config, $event, $logs, $counter);
                break;
            case Event::TYPE_CONDITION:
                $this->executeCondition($config, $event, $logs, $counter);
                break;
            case Event::TYPE_DECISION:
                $this->executeDecision($config, $event, $logs, $counter);
                break;
            default:
                throw new TypeNotFoundException("{$event->getEventType()} is not a valid event type");
        }
    }

    /**
     * @param Event           $event
     * @param ArrayCollection $contacts
     * @param bool            $isInactiveEvent
     */
    public function recordLogsAsExecutedForEvent(Event $event, ArrayCollection $contacts, $isInactiveEvent = false)
    {
        $config = $this->collector->getEventConfig($event);
        $logs   = $this->eventLogger->generateLogsFromContacts($event, $config, $contacts, $isInactiveEvent);

        // Save updated log entries and clear from memory
        $this->eventLogger->persistCollection($logs)
            ->clear();
    }

    /**
     * @param Event             $event
     * @param EvaluatedContacts $contacts
     * @param Counter|null      $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeContactsForDecisionPathChildren(Event $event, EvaluatedContacts $contacts, Counter $counter = null)
    {
        $childrenCounter = new Counter();
        $positive        = $contacts->getPassed();
        if ($positive->count()) {
            $this->logger->debug('CAMPAIGN: Contact IDs '.implode(',', $positive->getKeys()).' passed evaluation for event ID '.$event->getId());

            $children = $event->getPositiveChildren();
            $childrenCounter->advanceEvaluated($children->count());
            $this->executeContactsForChildren($children, $positive, $childrenCounter);
        }

        $negative = $contacts->getFailed();
        if ($negative->count()) {
            $this->logger->debug('CAMPAIGN: Contact IDs '.implode(',', $negative->getKeys()).' failed evaluation for event ID '.$event->getId());

            $children = $event->getNegativeChildren();

            $childrenCounter->advanceEvaluated($children->count());
            $this->executeContactsForChildren($children, $negative, $childrenCounter);
        }

        if ($counter) {
            $counter->advanceTotalEvaluated($childrenCounter->getTotalEvaluated());
            $counter->advanceTotalExecuted($childrenCounter->getTotalExecuted());
        }
    }

    /**
     * @param Event           $event
     * @param ArrayCollection $contacts
     * @param Counter|null    $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeContactsForConditionChildren(Event $event, ArrayCollection $contacts, Counter $counter = null)
    {
        $childrenCounter = new Counter();
        $conditions      = $event->getChildrenByEventType(Event::TYPE_CONDITION);
        $childrenCounter->advanceEvaluated($conditions->count());

        $this->logger->debug('CAMPAIGN: Evaluating '.$conditions->count().' conditions for action ID '.$event->getId());

        $this->executeContactsForChildren($conditions, $contacts, $childrenCounter);

        if ($counter) {
            $counter->advanceTotalEvaluated($childrenCounter->getTotalEvaluated());
            $counter->advanceTotalExecuted($childrenCounter->getTotalExecuted());
        }
    }

    /**
     * @param ArrayCollection $children
     * @param ArrayCollection $contacts
     * @param Counter         $childrenCounter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeContactsForChildren(ArrayCollection $children, ArrayCollection $contacts, Counter $childrenCounter)
    {
        foreach ($children as $child) {
            // Ignore decisions
            if (Event::TYPE_DECISION == $child->getEventType()) {
                $this->logger->debug('CAMPAIGN: Ignoring child event ID '.$child->getId().' as a decision');
                continue;
            }

            $executionDate = $this->scheduler->getExecutionDateTime($child, $this->now);

            $this->logger->debug(
                'CAMPAIGN: Event ID# '.$child->getId().
                ' to be executed on '.$executionDate->format('Y-m-d H:i:s')
            );

            if ($this->scheduler->shouldSchedule($executionDate, $this->now)) {
                $childrenCounter->advanceTotalScheduled($contacts->count());
                $this->scheduler->schedule($child, $executionDate, $contacts);
                continue;
            }

            $this->executeForContacts($child, $contacts, $childrenCounter);
        }
    }

    /**
     * @param ArrayCollection $children
     * @param ArrayCollection $contacts
     * @param Counter         $childrenCounter
     * @param bool            $validatingInaction
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function executeContactsForInactiveChildren(ArrayCollection $children, ArrayCollection $contacts, Counter $childrenCounter, \DateTime $earliestLastActiveDateTime)
    {
        $eventExecutionDates = $this->scheduler->getSortedExecutionDates($children, $earliestLastActiveDateTime);

        /** @var \DateTime $earliestExecutionDate */
        $earliestExecutionDate = reset($eventExecutionDates);

        foreach ($children as $child) {
            // Ignore decisions
            if (Event::TYPE_DECISION == $child->getEventType()) {
                $this->logger->debug('CAMPAIGN: Ignoring child event ID '.$child->getId().' as a decision');
                continue;
            }

            $executionDate = $this->scheduler->getExecutionDateForInactivity(
                $eventExecutionDates[$child->getId()],
                $earliestExecutionDate,
                $this->now
            );

            $this->logger->debug(
                'CAMPAIGN: Event ID# '.$child->getId().
                ' to be executed on '.$executionDate->format('Y-m-d H:i:s')
            );

            if ($this->scheduler->shouldSchedule($executionDate, $this->now)) {
                $childrenCounter->advanceTotalScheduled($contacts->count());
                $this->scheduler->schedule($child, $executionDate, $contacts, true);
                continue;
            }

            $this->executeForContacts($child, $contacts, $childrenCounter, true);
        }
    }

    /**
     * @param AbstractEventAccessor $config
     * @param Event                 $event
     * @param ArrayCollection       $logs
     * @param Counter|null          $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    private function executeAction(AbstractEventAccessor $config, Event $event, ArrayCollection $logs, Counter $counter = null)
    {
        $this->actionExecutioner->executeLogs($config, $logs);

        /** @var ArrayCollection $contacts */
        $contacts = $this->eventLogger->extractContactsFromLogs($logs);

        // Update and clear any pending logs
        $this->persistLogs($logs);

        // Process conditions that are attached to this action
        $this->executeContactsForConditionChildren($event, $contacts, $counter);
    }

    /**
     * @param AbstractEventAccessor $config
     * @param Event                 $event
     * @param ArrayCollection       $logs
     * @param Counter|null          $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    private function executeCondition(AbstractEventAccessor $config, Event $event, ArrayCollection $logs, Counter $counter = null)
    {
        $evaluatedContacts = $this->conditionExecutioner->executeLogs($config, $logs);

        // Update and clear any pending logs
        $this->persistLogs($logs);

        $this->executeContactsForDecisionPathChildren($event, $evaluatedContacts, $counter);
    }

    /**
     * @param AbstractEventAccessor $config
     * @param Event                 $event
     * @param ArrayCollection       $logs
     * @param Counter|null          $counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    private function executeDecision(AbstractEventAccessor $config, Event $event, ArrayCollection $logs, Counter $counter = null)
    {
        $evaluatedContacts = $this->decisionExecutioner->executeLogs($config, $logs);

        // Update and clear any pending logs
        $this->persistLogs($logs);

        $this->executeContactsForDecisionPathChildren($event, $evaluatedContacts, $counter);
    }

    /**
     * @param ArrayCollection $logs
     */
    private function persistLogs(ArrayCollection $logs)
    {
        if ($this->responses) {
            // Extract responses
            $this->responses->setFromLogs($logs);
        }

        // Save updated log entries and clear from memory
        $this->eventLogger->persistCollection($logs)
            ->clear();
    }
}
