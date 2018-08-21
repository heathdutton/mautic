<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'routes' => [
        'main' => [
            'mautic_integration_core' => [
                'path' => '/integration',
                'controller' => 'MauticIntegrationBundle:Default:index',
            ],
        ],
        'api' => [
            'mautic_integration_api_plugin_list' => [
                'path' => '/integration/plugin/list',
                'controller' => 'IntegrationsBundle:Api\Plugin:list',
            ],
        ],
    ],
    'menu' => [
    ],
    'services' => [
        'commands' => [
            'mautic.integrations.command.sync' => [
                'class'     => \MauticPlugin\IntegrationsBundle\Command\SyncCommand::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.integrations.sync.service',
                ],
                'tag' => 'console.command',
            ],
        ],
        'events' => [
            'mautic.integrations.lead.subscriber' => [
                'class'     => \MauticPlugin\IntegrationsBundle\EventListener\LeadSubscriber::class,
                'arguments' => [
                    'mautic.integrations.repository.field_change',
                    'mautic.integrations.helper.variable_expresser'
                ],
            ],
        ],
        'forms' => [
        ],
        'helpers' => [
            'mautic.integrations.helper.variable_expresser' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\VariableExpresser\VariableExpresserHelper::class
            ]
        ],
        'menus' => [
        ],
        'other' => [
            'mautic.service.encryption' => [
                'class' => \MauticPlugin\IntegrationsBundle\Facade\EncryptionService::class,
                'arguments' => [
                    'mautic.helper.encryption',
                ],
            ],
            'mautic.http.client' => [
                'class' => GuzzleHttp\Client::class
            ],
            'mautic.integrations.auth.factory' => [
                'class' => MauticPlugin\IntegrationsBundle\Auth\Factory::class,
            ],
            'mautic.integrations.auth.provider.oauth1a' => [
                'class' => MauticPlugin\IntegrationsBundle\Auth\Provider\OAuth1aProvider::class,
                'arguments' => [
                    'mautic.http.client',
                ],
                'tag' => 'mautic.integrations.auth.provider'
            ],
        ],
        'models' => [
        ],
        'validator' => [
        ],
        'repositories' => [
            'mautic.integrations.repository.field_change' => [
                'class'     => \Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \MauticPlugin\IntegrationsBundle\Entity\FieldChange::class,
                ],
            ],
        ],
        'sync' => [
            'mautic.integrations.helper.sync_judge' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncJudge\SyncJudge::class,
            ],
            'mautic.integrations.sync.data_exchange.mautic' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange::class,
                'arguments' => [
                    'mautic.integrations.helper.sync_judge',
                    'mautic.integrations.repository.field_change',
                    'mautic.lead.repository.lead',
                    'mautic.lead.model.lead',
                    'mautic.integrations.helper.variable_expresser',
                    'mautic.integrations.helper.sync_duplicity_finder',
                ],
            ],
            'mautic.integrations.helper.sync_process_factory' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncProcess\SyncProcessFactory::class,
            ],
            'mautic.integrations.sync.service' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncService\SyncService::class,
                'arguments' => [
                    'mautic.integrations.helper.sync_process_factory',
                    'mautic.integrations.helper.sync_date',
                    'mautic.integrations.sync.data_exchange.mautic',
                ],
            ],
            'mautic.integrations.helper.sync_date' => [
                'class'     => \MauticPlugin\IntegrationsBundle\Helpers\SyncDateHelper::class,
                'arguments' => [
                    'doctrine.dbal.default_connection',
                ],
            ],
            'mautic.integrations.helper.sync_duplicity_finder' => [
                'class' => \MauticPlugin\IntegrationBundle\Sync\Duplicity\DuplicityFinder::class,
                'arguments' => [

                ],
            ],
        ],
    ],

    'parameters' => [
        'plugin_dir' => '%kernel.root_dir%/../plugins',
    ],
];
