<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\DateRangeType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class DateRangeGroupByType.
 */
class DateRangeGroupByType extends DateRangeType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $options['choices'] = [
            // 'All Events' => 'All Events',
            // ''           => '--- By Source ---',
            'revenue'    => 'Revenue',
        ];

        if (!empty($options['choices'])) {
            $builder->add(
                'group_by',
                'choice',
                [
                    'choices'    => $options['choices'],
                    'attr'       => [
                        'class'   => 'form-control',
                    ],
                    'expanded'    => false,
                    'multiple'    => false,
                    'empty_data'  => null,
                    'required'    => false,
                    'disabled'    => false,
                    'placeholder' => 'mautic.campaign.stats.group.everything',
                    'group_by'    => function ($value, $key, $index) {
                        return 'mautic.campaign.stats.group.sources.by';
                    },
                ]
            );
        }
        unset($options['choices']);

        parent::buildForm($builder, $options);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'daterangegroupby';
    }
}
